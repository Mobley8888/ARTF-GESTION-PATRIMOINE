import 'bootstrap/dist/js/bootstrap.bundle.min.js';

document.querySelectorAll('[data-location-cascade]').forEach((cascade) => {
    const site = cascade.querySelector('[data-location-site]');
    const building = cascade.querySelector('[data-location-building]');
    const room = cascade.querySelector('[data-location-room]');
    const locationValue = cascade.querySelector('[data-location-value]');

    if (!site || !building || !room || !locationValue) {
        return;
    }

    const filterOptions = (select, parentId) => {
        let currentValueAvailable = false;

        Array.from(select.options).forEach((option) => {
            if (!option.value) {
                return;
            }

            const available = Boolean(parentId) && option.dataset.parent === parentId;
            option.hidden = !available;
            option.disabled = !available;
            currentValueAvailable = currentValueAvailable || (option.value === select.value && available);
        });

        if (!currentValueAvailable) {
            select.value = '';
        }
    };

    const updateLocationValue = () => {
        locationValue.value = room.value || building.value || site.value;
    };

    const updateRooms = () => {
        const roomParentId = building.value || site.value;
        filterOptions(room, roomParentId);
        room.disabled = !roomParentId;
        updateLocationValue();
    };

    const updateBuildings = () => {
        filterOptions(building, site.value);
        building.disabled = !site.value;
        updateRooms();
    };

    site.addEventListener('change', updateBuildings);
    building.addEventListener('change', updateRooms);
    room.addEventListener('change', updateLocationValue);
    updateBuildings();
});

document.querySelectorAll('[data-category-cascade]').forEach((cascade) => {
    const category = cascade.querySelector('[data-category-parent]');
    const subcategory = cascade.querySelector('[data-category-child]');

    if (!category || !subcategory) {
        return;
    }

    const updateSubcategories = () => {
        let currentValueAvailable = false;
        let availableCount = 0;

        Array.from(subcategory.options).forEach((option) => {
            if (!option.value) {
                return;
            }

            const available = Boolean(category.value) && option.dataset.parentCategory === category.value;
            option.hidden = !available;
            option.disabled = !available;
            availableCount += Number(available);
            currentValueAvailable = currentValueAvailable || (option.value === subcategory.value && available);
        });

        if (!currentValueAvailable) {
            subcategory.value = '';
        }

        subcategory.disabled = !category.value || availableCount === 0;
    };

    category.addEventListener('change', updateSubcategories);
    updateSubcategories();
});

document.querySelectorAll('[data-location-admin-form]').forEach((form) => {
    const type = form.querySelector('[data-location-admin-type]');
    const parent = form.querySelector('[data-location-admin-parent]');

    if (!type || !parent) {
        return;
    }

    const updateParentOptions = () => {
        const isBuilding = ['building', 'batiment'].includes(type.value);
        const isLocal = ['room', 'local'].includes(type.value);
        const expectedParentTypes = isBuilding ? ['site'] : (isLocal ? ['site', 'building'] : []);
        let currentValueAvailable = false;

        Array.from(parent.options).forEach((option) => {
            if (!option.value) {
                return;
            }

            const optionIsBuilding = ['building', 'batiment'].includes(option.dataset.type);
            const optionFamily = optionIsBuilding ? 'building' : option.dataset.type;
            const available = expectedParentTypes.includes(optionFamily);
            option.hidden = !available;
            option.disabled = !available;
            currentValueAvailable = currentValueAvailable || (option.value === parent.value && available);
        });

        if (!currentValueAvailable) {
            parent.value = '';
        }

        parent.disabled = expectedParentTypes.length === 0;
        parent.required = expectedParentTypes.length > 0;
    };

    type.addEventListener('change', updateParentOptions);
    updateParentOptions();
});

document.querySelectorAll('[data-agent-search]').forEach((input) => {
    const select = document.getElementById(input.dataset.agentSearch);

    if (!select) {
        return;
    }

    const normalize = (value) => value
        .toLocaleLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    input.addEventListener('input', () => {
        const terms = normalize(input.value.trim()).split(/\s+/).filter(Boolean);

        Array.from(select.options).forEach((option) => {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            const searchable = normalize(option.dataset.search || option.textContent);
            option.hidden = terms.length > 0 && !terms.every((term) => searchable.includes(term));
        });
    });
});
