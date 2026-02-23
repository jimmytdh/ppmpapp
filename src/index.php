<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>APP Procurement Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui']
                    },
                    boxShadow: {
                        premium: '0 10px 30px rgba(17, 24, 39, 0.18)'
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-100 via-cyan-50 to-amber-50 text-slate-800">
    <div class="mx-auto max-w-7xl px-4 py-8 lg:py-12">
        <div class="rounded-3xl border border-white/60 bg-white/80 p-6 shadow-premium backdrop-blur md:p-8">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight md:text-3xl">APP Procurement Manager</h1>
                    <p class="mt-1 text-sm text-slate-600">Create, update, and export procurement projects in APP format.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button id="openCreateModal" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">
                        + New Entry
                    </button>
                    <button id="openSignatoriesModal" class="rounded-xl bg-slate-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-600">
                        Signatories
                    </button>
                </div>
            </div>

            <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-900 text-xs uppercase tracking-wider text-slate-100">
                            <tr>
                                <th class="px-4 py-3 text-left">Project Title</th>
                                <th class="px-4 py-3 text-left">End User</th>
                                <th class="px-4 py-3 text-left">Type of Project</th>
                                <th class="px-4 py-3 text-left">General Description</th>
                                <th class="px-4 py-3 text-left">Mode</th>
                                <th class="px-4 py-3 text-left">EPA</th>
                                <th class="px-4 py-3 text-left">Estimated Budget</th>
                                <th class="px-4 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="projectsTableBody" class="divide-y divide-slate-100 bg-white text-sm"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="projectModal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-slate-900/50 p-4">
        <div class="w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-premium md:p-7">
            <div class="mb-5 flex items-center justify-between">
                <h2 id="modalTitle" class="text-xl font-bold">Create Project</h2>
                <button id="closeModal" class="rounded-lg px-3 py-1 text-slate-500 hover:bg-slate-100 hover:text-slate-900">Close</button>
            </div>
            <form id="projectForm" class="space-y-4">
                <input type="hidden" id="projectId" name="id">
                <div>
                    <label class="mb-1 block text-sm font-semibold">Project Title</label>
                    <input type="text" id="project_title" name="project_title" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none ring-cyan-200 focus:ring-2" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold">End User</label>
                    <input type="text" id="end_user" name="end_user" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none ring-cyan-200 focus:ring-2" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold">Type of Project</label>
                    <select id="type_of_project" name="type_of_project" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none ring-cyan-200 focus:ring-2" required>
                        <option value="Goods">Goods</option>
                        <option value="Infrastructure">Infrastructure</option>
                        <option value="Service">Service</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold">General Description</label>
                    <div class="mb-2 inline-flex overflow-hidden rounded-lg border border-slate-300">
                        <button type="button" class="rte-btn border-r border-slate-300 px-3 py-1.5 text-sm font-bold hover:bg-slate-50" data-cmd="bold">B</button>
                        <button type="button" class="rte-btn border-r border-slate-300 px-3 py-1.5 text-sm italic hover:bg-slate-50" data-cmd="italic">I</button>
                        <button type="button" class="rte-btn px-3 py-1.5 text-sm underline hover:bg-slate-50" data-cmd="underline">U</button>
                    </div>
                    <div id="general_description_editor" contenteditable="true" class="min-h-[130px] max-h-[260px] overflow-y-auto w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none ring-cyan-200 focus:ring-2"></div>
                    <textarea id="general_description" name="general_description" class="hidden"></textarea>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold">Mode of Procurement</label>
                        <select id="mode_of_procurement" name="mode_of_procurement" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none ring-cyan-200 focus:ring-2" required>
                            <option value="Public Bidding">Public Bidding</option>
                            <option value="Small Value Procurement">Small Value Procurement</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold">Covered by EPA</label>
                        <select id="covered_by_epa" name="covered_by_epa" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none ring-cyan-200 focus:ring-2" required>
                            <option value="Yes">Yes</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold">Estimated Budget</label>
                        <input type="number" id="estimated_budget" name="estimated_budget" min="0" step="0.01" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none ring-cyan-200 focus:ring-2" required>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" id="cancelModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-700">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="signatoriesModal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-slate-900/50 p-4">
        <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-premium md:p-7">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-xl font-bold">Signatories</h2>
                <button id="closeSignatoriesModal" class="rounded-lg px-3 py-1 text-slate-500 hover:bg-slate-100 hover:text-slate-900">Close</button>
            </div>
            <form id="signatoriesForm" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold">Prepared by:</label>
                        <input type="text" id="prepared_by_name" name="prepared_by_name" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none ring-cyan-200 focus:ring-2" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold">Designation:</label>
                        <input type="text" id="prepared_by_designation" name="prepared_by_designation" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none ring-cyan-200 focus:ring-2" required>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold">Submitted by:</label>
                        <input type="text" id="submitted_by_name" name="submitted_by_name" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none ring-cyan-200 focus:ring-2" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold">Designation:</label>
                        <input type="text" id="submitted_by_designation" name="submitted_by_designation" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none ring-cyan-200 focus:ring-2" required>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold">Date:</label>
                    <input type="date" id="sign_date" name="sign_date" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none ring-cyan-200 focus:ring-2">
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" id="cancelSignatoriesModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-700">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteConfirmModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-premium">
            <h3 class="text-lg font-bold text-slate-900">Delete Record</h3>
            <p class="mt-2 text-sm text-slate-600">This action cannot be undone. Are you sure you want to delete this entry?</p>
            <div class="mt-5 flex items-center justify-end gap-3">
                <button id="cancelDeleteBtn" type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Cancel</button>
                <button id="confirmDeleteBtn" type="button" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500">Delete</button>
            </div>
        </div>
    </div>

    <script src="assets/app.js"></script>
</body>
</html>
