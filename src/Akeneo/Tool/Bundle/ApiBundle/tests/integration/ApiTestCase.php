<?php

namespace Akeneo\Tool\Bundle\ApiBundle\tests\integration;

use Akeneo\Apps\Application\Command\CreateAppCommand;
use Akeneo\Apps\Application\Query\FindAnAppQuery;
use Akeneo\Apps\Domain\Model\Read\AppWithCredentials;
use Akeneo\Apps\Domain\Model\ValueObject\FlowType;
use Akeneo\Pim\Enrichment\Component\FileStorage;
use Akeneo\Test\Integration\Configuration;
use Akeneo\Test\IntegrationTestsBundle\Configuration\CatalogInterface;
use Akeneo\Tool\Bundle\ApiBundle\Stream\StreamResourceResponse;
use Akeneo\UserManagement\Component\Model\User;
use Akeneo\UserManagement\Component\Model\UserInterface;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Test case dedicated to PIM API interaction including authentication handling.
 *
 * @author    Yohan Blain <yohan.blain@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
abstract class ApiTestCase extends WebTestCase
{
    const USERNAME = 'admin';
    const PASSWORD = 'admin';

    /** @var KernelInterface */
    protected $testKernel;

    /** @var CatalogInterface */
    protected $catalog;

    /**
     * @return Configuration
     */
    abstract protected function getConfiguration();

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        static::bootKernel(['debug' => false]);
        $this->catalog = $this->get('akeneo_integration_tests.catalogs');

        $authenticator = $this->get('akeneo_integration_tests.security.system_user_authenticator');
        $authenticator->createSystemUser();

        $fixturesLoader = $this->get('akeneo_integration_tests.loader.fixtures_loader');
        $fixturesLoader->load($this->getConfiguration());

        $this->get('pim_connector.doctrine.cache_clearer')->clear();
    }

    /**
     * Adds a valid access token to the client, so it is included in all its requests.
     *
     * @param array  $options
     * @param array  $server
     * @param string $clientId
     * @param string $secret
     * @param string $username
     * @param string $password
     * @param string $accessToken
     * @param string $refreshToken
     *
     * @return Client
     */
    protected function createAuthenticatedClient(array $options = [], array $server = [], ?string $appCode = 'Test_API')
    {
        $options = array_merge($options, ['debug' => false]);

        $app = $this->createOAuthClient($appCode);
        $username = $appCode;
        $password = $appCode;

        list($accessToken, $refreshToken) = $this->authenticate($app->clientId(), $app->secret(), $username, $password);

        $client = static::createClient($options, $server);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$accessToken);

        $user = $this->get('pim_user.repository.user')->findOneByIdentifier($username);
        $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $this->get('security.token_storage')->setToken($token);

        $aclManager = $this->get('oro_security.acl.manager');
        $aclManager->clearCache();

        if (!isset($server['CONTENT_TYPE'])) {
            $client->setServerParameter('CONTENT_TYPE', 'application/json');
        }

        return $client;
    }

    /**
     * Creates a new OAuth client and returns its client id and secret.
     *
     * @param string $code
     *
     * @return App
     */
    protected function createOAuthClient(string $code): AppWithCredentials
    {
        $cmd = new CreateAppCommand($code, $code, FlowType::OTHER);
        $this->get('akeneo_app.application.handler.create_app')->handle($cmd);

        $query = new FindAnAppQuery($code);
        $app = $this->get('akeneo_app.application.handler.find_an_app')->handle($query);

        return $app;
    }

    /**
     * Authenticates a user by calling the token route and returns the access token and the refresh token.
     *
     * @param string $clientId
     * @param string $secret
     * @param string $username
     * @param string $password
     *
     * @return string[]
     */
    protected function authenticate($clientId, $secret, $username, $password)
    {
        $webClient = static::createClient(['debug' => false]);
        $webClient->request('POST', 'api/oauth/v1/token',
            [
                'username'   => $username,
                'password'   => $password,
                'grant_type' => 'password',
            ],
            [],
            [
                'PHP_AUTH_USER' => $clientId,
                'PHP_AUTH_PW'   => $secret,
                'CONTENT_TYPE'  => 'application/json',
            ]
        );

        $response = $webClient->getResponse();
        $responseBody = json_decode($response->getContent(), true);

        return [
            $responseBody['access_token'],
            $responseBody['refresh_token']
        ];
    }

    /**
     * @param string $service
     *
     * @return mixed
     */
    protected function get(string $service)
    {
        return self::$container->get($service);
    }

    /**
     * @param string $parameter
     *
     * @return mixed
     */
    protected function getParameter(string $parameter)
    {
        return self::$container->getParameter($parameter);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $connectionCloser = $this->get('akeneo_integration_tests.doctrine.connection.connection_closer');
        $connectionCloser->closeConnections();

        parent::tearDown();
    }

    /**
     * Look in every fixture directory if a fixture $name exists.
     * And return the pathname of the fixture if it exists.
     *
     * @param string $name
     *
     * @throws \Exception if no fixture $name has been found
     *
     * @return string
     */
    protected function getFixturePath(string $name): string
    {
        $configuration = $this->getConfiguration();
        foreach ($configuration->getFixtureDirectories() as $fixtureDirectory) {
            $path = $fixtureDirectory . DIRECTORY_SEPARATOR . $name;
            if (is_file($path) && false !== realpath($path)) {
                return realpath($path);
            }
        }

        throw new \Exception(sprintf('The fixture "%s" does not exist.', $name));
    }

    protected function getFileInfoKey(string $path): string
    {
        if (!is_file($path)) {
            throw new \Exception(sprintf('The path "%s" does not exist.', $path));
        }

        $fileStorer = $this->get('akeneo_file_storage.file_storage.file.file_storer');
        $fileInfo = $fileStorer->store(new \SplFileInfo($path), FileStorage::CATALOG_STORAGE_ALIAS);

        return $fileInfo->getKey();
    }

    /**
     * Execute a request where the response is streamed by chunk.
     *
     * The whole content of the request and the whole content of the response
     * are loaded in memory.
     * Therefore, do not use this function with an high input/output volumetry.
     *
     * @param string $method
     * @param string $uri
     * @param array  $parameters
     * @param array  $files
     * @param array  $server
     * @param string $content
     * @param bool   $changeHistory
     * @param string $username
     * @param string $password
     *
     * @return array
     */
    protected function executeStreamRequest(
        $method,
        $uri,
        array $parameters = [],
        array $files = [],
        array $server = [],
        $content = null,
        $changeHistory = true
    ) {
        $streamedContent = '';

        ob_start(function ($buffer) use (&$streamedContent) {
            $streamedContent .= $buffer;

            return '';
        });

        $client = $this->createAuthenticatedClient([], [], 'test');
        $client->setServerParameter('CONTENT_TYPE', StreamResourceResponse::CONTENT_TYPE);
        $client->request($method, $uri, $parameters, $files, $server, $content, $changeHistory);

        ob_end_flush();

        $response = [
            'http_response' => $client->getResponse(),
            'content'       => $streamedContent,
        ];

        return $response;
    }

    /**
     * See https://github.com/symfony/symfony/commit/76f6c97416aca79e24a5b3e20e182fd6cc064b69...
     *
     * @param string $string
     *
     * @return string
     */
    protected function encodeStringWithSymfonyUrlGeneratorCompatibility(string $string): string
    {
        $toReplace = [
            // RFC 3986 explicitly allows those in the query/fragment to reference other URIs unencoded
            '%2F' => '/',
            '%3F' => '?',
            // reserved chars that have no special meaning for HTTP URIs in a query or fragment
            // this excludes esp. "&", "=" and also "+" because PHP would treat it as a space (form-encoded)
            '%40' => '@',
            '%3A' => ':',
            '%21' => '!',
            '%3B' => ';',
            '%2C' => ',',
            '%2A' => '*',
        ];

        return strtr(rawurlencode($string), $toReplace);
    }
}
