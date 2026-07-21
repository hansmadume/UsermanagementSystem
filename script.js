const password = document.getElementById("password");
const showPassword = document.getElementById("showPassword");

if (showPassword && password) {
    showPassword.addEventListener("change", function () {
        password.type = this.checked ? "text" : "password";
    });
}

function getCsrfToken() {
    const el = document.getElementById('csrfToken')
        || document.querySelector('input[name="csrf"]');
    return el ? el.value : '';
}

async function ajaxJSON(url, method = 'POST', data = {}) {
    const formData = new FormData();
    for (const [k, v] of Object.entries(data)) {
        formData.append(k, v);
    }

    const res = await fetch(url, {
        method,
        body: formData,
        credentials: 'same-origin'
    });

    const json = await res.json().catch(() => null);

    if (!res.ok) {
        const msg = json?.message ? json.message : `Request failed (${res.status})`;
        throw new Error(msg);
    }

    return json;
}

function calculateAge(birthdayStr) {
    const birthDate = new Date(birthdayStr);
    const today = new Date();

    if (Number.isNaN(birthDate.getTime())) return null;

    let age = today.getFullYear() - birthDate.getFullYear();

    const hasHadBirthdayThisYear =
        today.getMonth() > birthDate.getMonth() ||
        (today.getMonth() === birthDate.getMonth() && today.getDate() >= birthDate.getDate());

    if (!hasHadBirthdayThisYear) {
        age--;
    }

    return age;
}

// ---------------------------------------------------------------------
// CROSS-PAGE CHECKBOX SELECTION
// Pagination used to be a full page reload, so checked checkboxes were
// lost the moment new HTML rendered. sessionStorage holds the
// authoritative set of selected record IDs regardless of which page is
// currently on screen; every checkbox interaction reads/writes it, and
// every page render (initial or AJAX) restores checkbox state from it.
// Bulk actions read from this set (not from
// document.querySelectorAll(':checked')) so a selection made on page 1
// is still included when you submit from page 2.
// ---------------------------------------------------------------------

const SELECTED_IDS_KEY = 'dashboardSelectedIds';

function getSelectedIds() {
    try {
        const raw = sessionStorage.getItem(SELECTED_IDS_KEY);
        return raw ? new Set(JSON.parse(raw)) : new Set();
    } catch {
        return new Set();
    }
}

function saveSelectedIds(idSet) {
    sessionStorage.setItem(SELECTED_IDS_KEY, JSON.stringify(Array.from(idSet)));
}

function clearSelectedIds() {
    sessionStorage.removeItem(SELECTED_IDS_KEY);
}

// Reflects the persisted selection onto whatever checkboxes are currently
// rendered (called on every page load, and after every AJAX table refresh).
function restoreCheckboxSelection() {
    const selected = getSelectedIds();
    document.querySelectorAll('.recordCheckbox').forEach((cb) => {
        cb.checked = selected.has(cb.value);
    });
    syncSelectAllState();
}

// "Select all" should reflect the *current page's* checkboxes: checked only
// if every row on this page is selected, indeterminate if some but not all
// are (a common pattern so the header checkbox doesn't lie about partial
// selection), unchecked otherwise.
function syncSelectAllState() {
    const selectAllEl = document.getElementById('selectAll');
    if (!selectAllEl) return;

    const checkboxes = Array.from(document.querySelectorAll('.recordCheckbox'));
    if (checkboxes.length === 0) {
        selectAllEl.checked = false;
        selectAllEl.indeterminate = false;
        return;
    }

    const checkedCount = checkboxes.filter((cb) => cb.checked).length;
    selectAllEl.checked = checkedCount === checkboxes.length;
    selectAllEl.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
}

function wireRecordCheckboxes() {
    document.querySelectorAll('.recordCheckbox').forEach((cb) => {
        if (cb.dataset.selectBound === '1') return;
        cb.dataset.selectBound = '1';

        cb.addEventListener('change', () => {
            const selected = getSelectedIds();
            if (cb.checked) {
                selected.add(cb.value);
            } else {
                selected.delete(cb.value);
            }
            saveSelectedIds(selected);
            syncSelectAllState();
        });
    });
}

// ---------------------------------------------------------------------
// PAGINATION (server-driven)
// api_search.php now returns page / record_pages / total_rows /
// record_per_page alongside rows, so the AJAX path and the full-page-load
// path (dashboard.php) agree on the same numbers. currentPage is the
// client's memory of "what page am I looking at" between refreshTable()
// calls; it's always overwritten with whatever the server actually served
// (json.page), since the server clamps out-of-range pages.
// ---------------------------------------------------------------------

let currentPage = 1;

function updateRecordCount({ total_rows, page, record_per_page }) {
    const el = document.getElementById('recordCount');
    if (!el) return;

    const start = total_rows === 0 ? 0 : (page - 1) * record_per_page + 1;
    const end = Math.min(page * record_per_page, total_rows);
    el.textContent = `Showing ${start}\u2013${end} of ${total_rows} registered entities`;
}

function renderPaginationBar({ page, record_pages }) {
    const controls = document.querySelector('.pagination-controls');
    if (!controls) return;

    let html = '';

    html += page > 1
        ? `<button type="button" class="page-nav" data-page="${page - 1}" aria-label="Previous page">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
           </button>`
        : `<button type="button" class="page-nav" aria-label="Previous page" disabled>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
           </button>`;

    for (let i = 1; i <= record_pages; i++) {
        html += i === page
            ? `<button type="button" class="page-btn active" aria-current="page">${i}</button>`
            : `<button type="button" class="page-btn" data-page="${i}">${i}</button>`;
    }

    html += page < record_pages
        ? `<button type="button" class="page-nav" data-page="${page + 1}" aria-label="Next page">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
           </button>`
        : `<button type="button" class="page-nav" aria-label="Next page" disabled>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
           </button>`;

    controls.innerHTML = html;

    controls.querySelectorAll('[data-page]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const searchInput = document.querySelector('form.search-form input[name="search"]');
            const search = searchInput ? searchInput.value.trim() : '';
            try {
                await refreshTable({ search, page: parseInt(btn.dataset.page, 10) });
            } catch (err) {
                alert(err.message || 'Failed to load page');
            }
        });
    });
}

function renderRowsToTbody(rows) {
    const tbody = document.getElementById('userTbody');
    if (!tbody) return;

    tbody.innerHTML = '';

    rows.forEach((row) => {
        const tr = document.createElement('tr');

        const age = calculateAge(row.birthday);

        tr.innerHTML = `
            <td>
                <input type="checkbox" class="recordCheckbox" name="selected_ids[]" value="${row.id}">
            </td>
            <td>${row.id}</td>
            <td>${escapeHtml(row.name || '')}</td>
            <td>${escapeHtml(row.birthday || '')}</td>
            <td>${age}</td>
            <td>${escapeHtml(row.gender || '')}</td>
            <td>${escapeHtml(row.email || '')}</td>
            <td>${escapeHtml(row.religion || '')}</td>
            <td>${escapeHtml(row.nationality || '')}</td>
            <td>${escapeHtml(row.address || '')}</td>
            <td>${escapeHtml(row.civil_status || '')}</td>
            <td>
                <a class="edit-btn" href="edit.php?id=${row.id}">Edit</a>
                <a class="delete-btn" href="delete.php?id=${row.id}" data-delete-id="${row.id}">Delete</a>
            </td>
        `;

        tbody.appendChild(tr);
    });

    wireDeleteButtons();
    wireSelectAll();
    wireRecordCheckboxes();
    restoreCheckboxSelection();
}


function escapeHtml(str) {
    return String(str)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}




function wireSelectAll() {
    const selectAllEl = document.getElementById('selectAll');
    if (!selectAllEl) return;

    selectAllEl.onchange = () => {
        const selected = getSelectedIds();
        const checkboxes = document.querySelectorAll('.recordCheckbox');

        checkboxes.forEach((checkbox) => {
            checkbox.checked = selectAllEl.checked;
            if (selectAllEl.checked) {
                selected.add(checkbox.value);
            } else {
                selected.delete(checkbox.value);
            }
        });

        saveSelectedIds(selected);
    };
}

// NOTE: dashboard.php also used to wire select-all inline; that's been
// removed so this is the single wiring path (it now also has to keep
// sessionStorage in sync, so two independent copies would fight each other).


function wireDeleteButtons() {
    document.querySelectorAll('a.delete-btn[data-delete-id]').forEach((a) => {
        if (a.dataset.bound === '1') return;
        a.dataset.bound = '1';

        a.addEventListener('click', async (e) => {
            e.preventDefault();
            const id = a.dataset.deleteId;
            if (!id) return;
            if (!confirm('Delete this record?')) return;

            try {
                const csrf = getCsrfToken();
                if (!csrf) {
                    alert('CSRF token missing from page. Reload and try again.');
                    return;
                }

                await ajaxJSON('api_delete.php', 'POST', { id, csrf });

                // Row is gone - drop it from the persisted selection too,
                // otherwise a stale id lingers in sessionStorage forever.
                const selected = getSelectedIds();
                selected.delete(id);
                saveSelectedIds(selected);

                const searchInput = document.querySelector('form.search-form input[name="search"]');
                const searchTerm = searchInput ? searchInput.value.trim() : '';
                // Stay on the same page after a single-row delete - if that
                // page is now past the end (e.g. deleted the last row on the
                // last page), api_search.php clamps it back for us and we
                // pick up the clamped value from json.page.
                await refreshTable({ search: searchTerm, page: currentPage });
            } catch (err) {
                alert(err.message || 'Delete failed');
            }
        });
    });
}

async function refreshTable({ search = '', page = currentPage } = {}) {
    const json = await ajaxJSON('api_search.php', 'POST', { search, page });
    if (json?.success) {
        currentPage = json.page;
        renderRowsToTbody(json.rows || []);
        updateRecordCount(json);
        renderPaginationBar(json);
    }
}

function validateAddUserForm(dashboard) {
    let valid = true;

    const setError = (id, msg) => {
        const el = document.getElementById(id);
        if (el) el.textContent = msg;
    };

    ['nameError', 'birthdayError', 'genderError', 'emailError', 'religionError', 'nationalityError', 'addressError', 'civilStatusError']
        .forEach((id) => setError(id, ''));

    const name = dashboard.querySelector('input[name="name"]').value.trim();
    const birthday = dashboard.querySelector('input[name="birthday"]').value.trim();
    const gender = dashboard.querySelector('select[name="gender"]').value;
    const email = dashboard.querySelector('input[name="email"]').value.trim();
    const religion = dashboard.querySelector('input[name="religion"]').value.trim();
    const nationality = dashboard.querySelector('input[name="nationality"]').value.trim();
    const address = dashboard.querySelector('input[name="address"]').value.trim();
    const civilStatus = dashboard.querySelector('select[name="civil_status"]').value;

    const namePattern = /^[A-Za-zÀ-ÿ' -]+$/;
    const nameParts = name.split(/\s+/);

    if (name === '') {
        setError('nameError', 'Name is required.');
        valid = false;
    } else if (!namePattern.test(name)) {
        setError('nameError', "Name can only contain letters, spaces, apostrophes (') and hyphens (-).");
        valid = false;
    } else if (nameParts.length < 2) {
        setError('nameError', 'Please enter your first and last name. Middle name is optional.');
        valid = false;
    }

    if (birthday === '') {
        setError('birthdayError', 'Birthday is required.');
        valid = false;
    }

    if (gender === '') {
        setError('genderError', 'Please select a gender.');
        valid = false;
    }

    // simplified, non-backtracking pattern for basic email validation: local
    // and domain parts cannot contain spaces or @; require a TLD of at least
    // 2 letters to avoid pathological cases
    const emailPattern = /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/;
    if (email === '') {
        setError('emailError', 'Email is required.');
        valid = false;
    } else if (!emailPattern.test(email)) {
        setError('emailError', 'Invalid email address.');
        valid = false;
    }

    if (religion === '') {
        setError('religionError', 'Religion is required.');
        valid = false;
    }

    if (nationality === '') {
        setError('nationalityError', 'Nationality is required.');
        valid = false;
    }

    if (address === '') {
        setError('addressError', 'Address is required.');
        valid = false;
    }

    if (civilStatus === '') {
        setError('civilStatusError', 'Please select a civil status.');
        valid = false;
    }

    return valid;
}

(function () {
    const dashboard = document.querySelector('form#userForm');
    const searchForm = document.querySelector('form.search-form');

    if (searchForm) {
        searchForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = searchForm.querySelector('input[name="search"]');
            const search = input ? input.value.trim() : '';
            try {
                // New filter -> back to page 1, same as dashboard.php's
                // full-page-load behavior when the search query changes.
                await refreshTable({ search, page: 1 });
            } catch (err) {
                alert(err.message || 'Search failed');
            }
        });
    }

    if (dashboard) {
        dashboard.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!validateAddUserForm(dashboard)) {
                return;
            }

            const csrf = getCsrfToken();
            if (!csrf) {
                alert('CSRF token missing from page. Reload and try again.');
                return;
            }

            try {
                const payload = {
                    csrf,
                    name: dashboard.querySelector('input[name="name"]').value,
                    birthday: dashboard.querySelector('input[name="birthday"]').value,
                    gender: dashboard.querySelector('select[name="gender"]').value,
                    email: dashboard.querySelector('input[name="email"]').value,
                    religion: dashboard.querySelector('input[name="religion"]').value,
                    nationality: dashboard.querySelector('input[name="nationality"]').value,
                    address: dashboard.querySelector('input[name="address"]').value,
                    civil_status: dashboard.querySelector('select[name="civil_status"]').value
                };

                const json = await ajaxJSON('api_insert.php', 'POST', payload);
                if (json?.success) {
                    dashboard.reset();
                    const searchInput = document.querySelector('form.search-form input[name="search"]');
                    const searchTerm = searchInput ? searchInput.value.trim() : '';
                    // A new row was added - record_pages may have grown, so
                    // let api_search.php recompute it; jump to the last page
                    // so the newly added row is visible without the user
                    // having to click Next themselves.
                    const probe = await ajaxJSON('api_search.php', 'POST', { search: searchTerm, page: 999999 });
                    const targetPage = probe?.success ? probe.record_pages : 1;
                    await refreshTable({ search: searchTerm, page: targetPage });
                }
            } catch (err) {
                alert(err.message || 'Add failed');
            }
        });
    }

    const bulkForm = document.querySelector('form[action="delete_selected.php"]');
    if (bulkForm) {
        bulkForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const errorEl = document.getElementById('bulkDeleteError');
            if (errorEl) {
                errorEl.style.display = 'none';
                errorEl.textContent = '';
            }

            // Read from the persisted selection, not just the checkboxes
            // currently in the DOM - the whole point is that rows checked
            // on an earlier page are still included here.
            const ids = Array.from(getSelectedIds());
            if (ids.length === 0) {
                alert('Select at least one record to delete.');
                return;
            }

            try {
                const csrf = getCsrfToken();
                if (!csrf) {
                    const msg = 'CSRF token missing from page. Reload and try again.';
                    if (errorEl) {
                        errorEl.textContent = msg;
                        errorEl.style.display = 'block';
                    }
                    alert(msg);
                    return;
                }

                for (const id of ids) {
                    await ajaxJSON('api_delete.php', 'POST', { id, csrf });
                }

                clearSelectedIds();

                const searchInput = document.querySelector('form.search-form input[name="search"]');
                const searchTerm = searchInput ? searchInput.value.trim() : '';
                // Rows are gone - record_pages may have shrunk; let
                // api_search.php clamp currentPage back into range for us.
                await refreshTable({ search: searchTerm, page: currentPage });
            } catch (err) {
                const msg = err?.message || 'Bulk delete failed';
                if (errorEl) {
                    errorEl.textContent = msg;
                    errorEl.style.display = 'block';
                }
                alert(msg);
            }
        });
    }


})();

function printSelectedUsers() {

    const checked = document.querySelectorAll(".recordCheckbox:checked");

    if (checked.length === 0) {
        alert("Please select at least one user.");
        return;
    }

    const selectedCount = getSelectedIds().size;
    if (selectedCount > checked.length) {
        // Some selected rows belong to other pages and aren't in the DOM,
        // so their data can't be read for the printout. Rather than
        // silently drop them, tell the person what's happening.
        alert(
            `${selectedCount} records are selected, but only the ${checked.length} on this page can be printed right now. ` +
            "Printing across pages isn't supported yet - showing this page's selection only."
        );
    }

    let rowsHtml = "";

    checked.forEach(cb => {

        const td = cb.closest("tr").querySelectorAll("td");

        rowsHtml += `
        <tr>
            <td>${td[1].innerText}</td>
            <td>${td[2].innerText}</td>
            <td>${td[3].innerText}</td>
            <td>${td[4].innerText}</td>
            <td>${td[5].innerText}</td>
            <td>${td[6].innerText}</td>
            <td>${td[7].innerText}</td>
            <td>${td[8].innerText}</td>
            <td>${td[9].innerText}</td>
            <td>${td[10].innerText}</td>
        </tr>`;
    });

    const html = `<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Selected Users</title>

        <style>
            body{
                font-family:Arial;
                padding:20px;
            }

            table{
                width:100%;
                border-collapse:collapse;
            }

            th,td{
                border:1px solid #000;
                padding:8px;
            }

            th{
                background:#f2f2f2;
            }
        </style>

    </head>
    <body>

    <table>

    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Birthday</th>
        <th>Age</th>
        <th>Gender</th>
        <th>Email</th>
        <th>Religion</th>
        <th>Nationality</th>
        <th>Address</th>
        <th>Civil Status</th>
    </tr>
    ${rowsHtml}
    </table>
    </body>
    </html>`;

    // Using a Blob URL + window.open(url) instead of window.open("") +
    // document.write(). document.write() into an about:blank popup can
    // silently drop the injected <style> block (some browsers apply the
    // opener page's CSP to about:blank popups) and, separately, assigning
    // win.onload AFTER document.write()/close() is unreliable because the
    // load event can already have fired by the time the handler is
    // attached - so the auto-print never ran. A Blob URL is a real
    // navigation, so styles apply consistently and the load event fires
    // normally after we've already attached the listener.
    const blob = new Blob([html], { type: "text/html" });
    const url = URL.createObjectURL(blob);

    const win = window.open(url, "_blank");
    if (!win) {
        alert("Popup blocked. Please allow popups.");
        URL.revokeObjectURL(url);
        return;
    }

    win.addEventListener("load", function () {
        win.focus();
        win.print();
    });

    win.addEventListener("afterprint", function () {
        win.close();
        URL.revokeObjectURL(url);
    });

    // Fallback: if the print dialog is dismissed without firing
    // "afterprint" (happens in some browsers), still release the Blob URL.
    setTimeout(() => URL.revokeObjectURL(url), 60000);
}

// Builds a PDF from the checked table rows using jsPDF + jspdf-autotable
// (loaded via <script> tags in dashboard.php). Mirrors printSelectedUsers()'s
// row-gathering logic but writes a downloadable .pdf file instead of opening
// a print dialog. Same cross-page-data limitation as printSelectedUsers()
// applies here: only rows currently in the DOM can be read.
function downloadSelectedUsersPDF() {

    const checked = document.querySelectorAll(".recordCheckbox:checked");

    if (checked.length === 0) {
        alert("Please select at least one user.");
        return;
    }

    const selectedCount = getSelectedIds().size;
    if (selectedCount > checked.length) {
        alert(
            `${selectedCount} records are selected, but only the ${checked.length} on this page can be included right now. ` +
            "Downloading a PDF across pages isn't supported yet - including this page's selection only."
        );
    }

    if (!window.jspdf || !window.jspdf.jsPDF) {
        alert("PDF library failed to load. Please check your connection and try again.");
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: "landscape" });

    const head = [[
        "ID", "Name", "Birthday", "Age", "Gender",
        "Email", "Religion", "Nationality", "Address", "Civil Status"
    ]];

    const body = [];

    checked.forEach(cb => {
        const td = cb.closest("tr").querySelectorAll("td");
        body.push([
            td[1].innerText,
            td[2].innerText,
            td[3].innerText,
            td[4].innerText,
            td[5].innerText,
            td[6].innerText,
            td[7].innerText,
            td[8].innerText,
            td[9].innerText,
            td[10].innerText
        ]);
    });

    doc.autoTable({
        head,
        body,
        startY: 14,
        styles: { fontSize: 9, cellPadding: 3 },
        headStyles: { fillColor: [15, 55, 139] },
        margin: { left: 10, right: 10 }
    });

    const timestamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-");
    doc.save(`selected-users-${timestamp}.pdf`);
}

window.addEventListener('beforeunload', function () {

    sessionStorage.setItem('scrollPosition', window.scrollY);
});

window.addEventListener('load', function () {
    const pos = sessionStorage.getItem('scrollPosition');
    if (pos !== null) {
        window.scrollTo(0, pos);
        sessionStorage.removeItem('scrollPosition');
    }
});

function initializePrint() {
    const btn = document.getElementById('printSelectedBtn');
    if (!btn) return;
    btn.addEventListener('click', printSelectedUsers);
}

function initializeDownloadPdf() {
    const btn = document.getElementById('downloadPdfBtn');
    if (!btn) return;
    btn.addEventListener('click', downloadSelectedUsersPDF);
}

document.addEventListener('DOMContentLoaded', function () {
    // Keep table wiring working after AJAX refreshes (renderRowsToTbody calls these too),
    // but also wire initial state once at startup.
    wireSelectAll();
    wireDeleteButtons();
    wireRecordCheckboxes();
    restoreCheckboxSelection();
    initializePrint();
    initializeDownloadPdf();

    // dashboard.php's initial render already has a correct pagination bar
    // and record count baked into the HTML (from $page/$record_pages
    // computed server-side), so we don't touch either on first load -
    // only AJAX-driven refreshes call renderPaginationBar/updateRecordCount.
});