const Filter = require('./filter.decorator')

const FilterList = async (nodeElement, createElementDecorator, parent) => {
  const children = {
    'Filter':  {
      selector: '.AknFilterBox-filterContainer.filter-item',
      decorator: Filter,
      multiple: true
    },
  };

  const getMatchingFilter = async (childFilters, name) => {
    const filters = await Promise.all(childFilters)
    let matchingFilter = null;

    for (let i = 0; i < filters.length;i++) {
      const filterName = await filters[i].getName()
      if (filterName === name) {
        matchingFilter = filters[i]
        break;
      }
    }

    if (matchingFilter === null) {
      throw Error(`Can't find filter ${name}`)
    }

    return matchingFilter;
  }

  const resetFilters = async (filters) => {
    for (let i = 0; i < filters.length; i++) {
      const getChildren = createElementDecorator(children);
      try {
        const matchingFilter = await getMatchingFilter(await getChildren(parent, 'Filter'), filters[i])
        await matchingFilter.remove();
      } catch (e) {}
    }
  }

  const setFilterValue = async (name, operator, value) =>  {
    const getChildren = createElementDecorator(children);
    const matchingFilter = await getMatchingFilter(await getChildren(parent, 'Filter'), name)
    await matchingFilter.setValue(operator, value)
    await parent.waitFor(100);
  }

  return { setFilterValue, resetFilters };
};

module.exports = FilterList;