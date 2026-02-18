/* global $ */
(function () {
    const modal = $('#projectModal');
    const form = $('#projectForm');
    const tbody = $('#projectsTableBody');
    const descriptionEditor = $('#general_description_editor');
    const signatoriesModal = $('#signatoriesModal');
    const signatoriesForm = $('#signatoriesForm');
    const deleteConfirmModal = $('#deleteConfirmModal');
    const DESCRIPTION_WORD_LIMIT = 20;
    let pendingDeleteId = null;

    function money(value) {
        const n = Number(value || 0);
        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(text) {
        return $('<div>').text(text ?? '').html();
    }

    function sanitizeRichHtml(inputHtml) {
        const allowed = new Set(['B', 'I', 'U', 'BR']);
        const root = document.createElement('div');
        root.innerHTML = String(inputHtml ?? '')
            .replace(/\r?\n/g, '<br>')
            .replace(/<\/(div|p|li|h[1-6])>/gi, '<br>')
            .replace(/<(div|p|li|h[1-6])[^>]*>/gi, '');

        function clean(node) {
            Array.from(node.childNodes).forEach((child) => {
                if (child.nodeType === Node.TEXT_NODE) {
                    return;
                }
                if (child.nodeType !== Node.ELEMENT_NODE) {
                    child.remove();
                    return;
                }

                clean(child);
                const tag = child.tagName.toUpperCase();
                if (!allowed.has(tag)) {
                    while (child.firstChild) {
                        node.insertBefore(child.firstChild, child);
                    }
                    child.remove();
                    return;
                }

                Array.from(child.attributes).forEach((attr) => child.removeAttribute(attr.name));
            });
        }

        clean(root);
        return root.innerHTML;
    }

    function toEditorHtml(rawValue) {
        return sanitizeRichHtml(String(rawValue ?? '').replace(/\r?\n/g, '<br>'));
    }

    function htmlToPlainText(inputHtml) {
        const div = document.createElement('div');
        div.innerHTML = String(inputHtml ?? '').replace(/<br\s*\/?>/gi, '\n');
        return (div.textContent || '').trim();
    }

    function formatDescription(rawHtml) {
        const safeHtml = sanitizeRichHtml(rawHtml);
        const plain = htmlToPlainText(safeHtml);
        const words = plain ? plain.split(/\s+/) : [];
        const fullHtml = safeHtml;

        if (words.length <= DESCRIPTION_WORD_LIMIT) {
            return fullHtml;
        }

        const shortText = words.slice(0, DESCRIPTION_WORD_LIMIT).join(' ') + '...';
        const shortHtml = escapeHtml(shortText).replace(/\r?\n/g, '<br>');

        return `
            <span class="desc-text">${shortHtml}</span>
            <button
                type="button"
                class="toggle-desc ml-1 font-semibold text-cyan-700 hover:text-cyan-500"
                data-expanded="false"
                data-short="${encodeURIComponent(shortHtml)}"
                data-full="${encodeURIComponent(fullHtml)}"
            >
                See more..
            </button>
        `;
    }

    function resetForm() {
        form[0].reset();
        $('#projectId').val('');
        $('#general_description').val('');
        descriptionEditor.html('');
    }

    function openModal(title) {
        $('#modalTitle').text(title);
        modal.removeClass('hidden').addClass('flex');
    }

    function closeModal() {
        modal.addClass('hidden').removeClass('flex');
        resetForm();
    }

    function openSignatoriesModal() {
        signatoriesModal.removeClass('hidden').addClass('flex');
    }

    function closeSignatoriesModal() {
        signatoriesModal.addClass('hidden').removeClass('flex');
        if (signatoriesForm.length) {
            signatoriesForm[0].reset();
        }
    }

    function openDeleteConfirm(id) {
        pendingDeleteId = id;
        deleteConfirmModal.removeClass('hidden').addClass('flex');
    }

    function closeDeleteConfirm() {
        pendingDeleteId = null;
        deleteConfirmModal.addClass('hidden').removeClass('flex');
    }

    function deleteRowById(id) {
        $.ajax({
            url: 'api.php?action=delete',
            method: 'POST',
            data: { id },
            dataType: 'json'
        }).done((res) => {
            if (!res.ok) {
                alert(res.message || 'Delete failed');
                return;
            }
            loadRows();
        }).fail(() => alert('Delete failed'));
    }

    function renderRows(rows) {
        if (!rows.length) {
            tbody.html('<tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No records yet.</td></tr>');
            return;
        }

        const html = rows.map((row) => `
            <tr>
                <td class="px-4 py-3 align-top font-semibold text-slate-800">${escapeHtml(row.project_title)}</td>
                <td class="px-4 py-3 align-top">${escapeHtml(row.end_user)}</td>
                <td class="px-4 py-3 align-top text-slate-700">${formatDescription(row.general_description)}</td>
                <td class="px-4 py-3 align-top">${escapeHtml(row.mode_of_procurement)}</td>
                <td class="px-4 py-3 align-top">${escapeHtml(row.covered_by_epa)}</td>
                <td class="px-4 py-3 align-top font-semibold">${money(row.estimated_budget)}</td>
                <td class="px-4 py-3 align-top">
                    <div class="inline-flex overflow-hidden rounded-lg border border-slate-300 shadow-sm">
                        <button class="app-btn border-r border-slate-300 bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500" data-id="${row.id}">APP</button>
                        <button class="edit-btn border-r border-slate-300 bg-cyan-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-cyan-500" data-id="${row.id}">Edit</button>
                        <button class="delete-btn bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-500" data-id="${row.id}">Delete</button>
                    </div>
                </td>
            </tr>
        `).join('');

        tbody.html(html);
    }

    function loadRows() {
        $.getJSON('api.php', { action: 'list' })
            .done((res) => {
                if (!res.ok) {
                    alert(res.message || 'Failed to load data');
                    return;
                }
                renderRows(res.data || []);
            })
            .fail(() => alert('Failed to load data'));
    }

    $('#openCreateModal').on('click', function () {
        resetForm();
        openModal('Create Project');
    });

    $('#closeModal, #cancelModal').on('click', closeModal);

    $('#openSignatoriesModal').on('click', function () {
        $.getJSON('api.php', { action: 'get_signatories' })
            .done((res) => {
                if (!res.ok) {
                    alert(res.message || 'Failed to load signatories');
                    return;
                }
                const s = res.data || {};
                $('#prepared_by_name').val(s.prepared_by_name || '');
                $('#prepared_by_designation').val(s.prepared_by_designation || '');
                $('#submitted_by_name').val(s.submitted_by_name || '');
                $('#submitted_by_designation').val(s.submitted_by_designation || '');
                $('#sign_date').val(s.sign_date || '');
                openSignatoriesModal();
            })
            .fail(() => alert('Failed to load signatories'));
    });

    $('#closeSignatoriesModal, #cancelSignatoriesModal').on('click', closeSignatoriesModal);
    $('#cancelDeleteBtn').on('click', closeDeleteConfirm);

    form.on('submit', function (e) {
        e.preventDefault();
        const sanitizedDescription = sanitizeRichHtml(descriptionEditor.html());
        const descriptionPlain = htmlToPlainText(sanitizedDescription);
        if (!descriptionPlain) {
            alert('General Description is required.');
            descriptionEditor.trigger('focus');
            return;
        }
        $('#general_description').val(sanitizedDescription);

        const id = Number($('#projectId').val() || 0);
        const action = id > 0 ? 'update' : 'create';
        $.ajax({
            url: `api.php?action=${action}`,
            method: 'POST',
            data: form.serialize(),
            dataType: 'json'
        }).done((res) => {
            if (!res.ok) {
                alert(res.message || 'Save failed');
                return;
            }
            closeModal();
            loadRows();
        }).fail((xhr) => {
            let message = 'Save failed';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            alert(message);
        });
    });

    signatoriesForm.on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: 'api.php?action=save_signatories',
            method: 'POST',
            data: signatoriesForm.serialize(),
            dataType: 'json'
        }).done((res) => {
            if (!res.ok) {
                alert(res.message || 'Failed to save signatories');
                return;
            }
            closeSignatoriesModal();
        }).fail((xhr) => {
            let message = 'Failed to save signatories';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            alert(message);
        });
    });

    tbody.on('click', '.edit-btn', function () {
        const id = Number($(this).data('id'));
        $.getJSON('api.php', { action: 'get', id })
            .done((res) => {
                if (!res.ok) {
                    alert(res.message || 'Failed to fetch item');
                    return;
                }
                const row = res.data;
                $('#projectId').val(row.id);
                $('#project_title').val(row.project_title);
                $('#end_user').val(row.end_user);
                descriptionEditor.html(toEditorHtml(row.general_description || ''));
                $('#general_description').val(toEditorHtml(row.general_description || ''));
                $('#mode_of_procurement').val(row.mode_of_procurement);
                $('#covered_by_epa').val(row.covered_by_epa);
                $('#estimated_budget').val(row.estimated_budget);
                openModal('Edit Project');
            })
            .fail(() => alert('Failed to fetch item'));
    });

    tbody.on('click', '.delete-btn', function () {
        const id = Number($(this).data('id'));
        openDeleteConfirm(id);
    });

    $('#confirmDeleteBtn').on('click', function () {
        if (!pendingDeleteId) {
            closeDeleteConfirm();
            return;
        }
        const id = pendingDeleteId;
        closeDeleteConfirm();
        deleteRowById(id);
    });

    tbody.on('click', '.app-btn', function () {
        const id = Number($(this).data('id'));
        const url = `print_v2.php?ids[]=${encodeURIComponent(id)}`;
        window.open(url, '_blank', 'noopener');
    });

    tbody.on('click', '.toggle-desc', function () {
        const btn = $(this);
        const expanded = btn.attr('data-expanded') === 'true';
        const shortHtml = decodeURIComponent(btn.attr('data-short') || '');
        const fullHtml = decodeURIComponent(btn.attr('data-full') || '');
        const textTarget = btn.siblings('.desc-text');

        if (!expanded) {
            textTarget.html(fullHtml);
            btn.text('See less').attr('data-expanded', 'true');
            return;
        }

        textTarget.html(shortHtml);
        btn.text('See more..').attr('data-expanded', 'false');
    });

    $('.rte-btn').on('click', function () {
        const cmd = String($(this).data('cmd') || '');
        descriptionEditor.trigger('focus');
        document.execCommand(cmd, false, null);
    });

    loadRows();
})();
