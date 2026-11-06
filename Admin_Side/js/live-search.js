// Filter marked admin table rows as the search query changes.
document.querySelectorAll("[data-admin-live-search]").forEach(form => {
    const input = form.querySelector("[data-admin-live-search-input]");
    const table = form.closest(".panel")?.querySelector("table");

    if (!input || !table) {
        return;
    }

    const body = table.tBodies[0];
    if (!body) {
        return;
    }

    const rows = Array.from(body.querySelectorAll("[data-admin-search-row]"));
    const categoryFilters = form.closest(".panel")
        ?.querySelector("[data-admin-category-filters]");
    const categoryButtons = categoryFilters
        ? Array.from(categoryFilters.querySelectorAll("[data-admin-category-filter]"))
        : [];
    const emptyRow = document.createElement("tr");
    const emptyCell = document.createElement("td");
    emptyRow.hidden = true;
    emptyRow.className = "empty admin-live-search-empty";
    emptyCell.colSpan = table.tHead?.rows[0]?.cells.length || 1;
    emptyCell.textContent = "No matching results.";
    emptyRow.append(emptyCell);
    body.append(emptyRow);

    function updateSearchUrl() {
        const url = new URL(window.location.href);
        const value = input.value.trim();

        if (value) {
            url.searchParams.set(input.name, value);
        } else {
            url.searchParams.delete(input.name);
        }

        window.history.replaceState(null, "", url);
    }

    function normalizeCategory(category) {
        const normalized = String(category || "")
            .trim()
            .toLocaleLowerCase()
            .replace(/\s+/g, " ");

        if (["drinks", "beverage"].includes(normalized)) return "beverage";
        if (["donut", "donuts"].includes(normalized)) return "donut";
        if (["meryenda", "meryenda / dessert"].includes(normalized)) {
            return "meryenda / dessert";
        }

        return normalized;
    }

    function filterRows() {
        const query = input.value.trim().toLocaleLowerCase();
        const selectedCategory = categoryButtons.find(button =>
            button.classList.contains("active")
        )?.dataset.adminCategoryFilter || "All";
        const selectedCategoryKey = normalizeCategory(selectedCategory);
        let visibleCount = 0;

        rows.forEach(row => {
            const searchableText = (
                row.dataset.adminSearchRow || ""
            ).toLocaleLowerCase();
            const matchesSearch = !query || searchableText.includes(query);
            const rowCategory = normalizeCategory(row.dataset.adminCategory);
            const matchesCategory = selectedCategoryKey === "all" ||
                rowCategory === selectedCategoryKey;
            const matches = matchesSearch && matchesCategory;
            row.hidden = !matches;

            if (matches) {
                visibleCount += 1;
            }
        });

        body.querySelectorAll("tr").forEach(row => {
            if (!row.matches("[data-admin-search-row], .admin-live-search-empty")) {
                row.hidden = Boolean(query) || selectedCategoryKey !== "all";
            }
        });

        emptyRow.hidden = (!query && selectedCategoryKey === "all") ||
            visibleCount > 0;
        updateSearchUrl();
    }

    categoryButtons.forEach(button => {
        button.addEventListener("click", () => {
            categoryButtons.forEach(categoryButton => {
                const isActive = categoryButton === button;
                categoryButton.classList.toggle("active", isActive);
                categoryButton.setAttribute("aria-pressed", String(isActive));
            });

            filterRows();
        });
    });

    input.addEventListener("input", filterRows);
    form.addEventListener("submit", event => {
        if (form.querySelector('select[name="status"]')) {
            return;
        }

        event.preventDefault();
        filterRows();
    });

    filterRows();
});
