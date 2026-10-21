<dialog id="backup-folder-picker" class="rounded-xl border border-slate-200 bg-white p-5" style="width:min(92vw,48rem);max-height:85vh" aria-labelledby="backup-folder-title">
    <div class="mb-4 flex items-center justify-between gap-3"><h2 id="backup-folder-title" class="text-lg font-bold">Choose backup folder</h2><button type="button" id="close-backup-picker" class="rounded-lg border px-3 py-1" aria-label="Close folder picker">Close</button></div>
    <p class="mb-4 text-sm text-slate-500">Folders on the PC running Laravel. USB and external drives appear while connected.</p>
    <label class="block text-sm" for="backup-folder-drives">Drive or home folder</label><select id="backup-folder-drives" class="form-input mb-3"></select>
    <form id="backup-folder-navigation" class="mb-3 flex flex-wrap gap-2"><label class="flex-1" for="backup-folder-path"><span class="sr-only">Folder path</span><input id="backup-folder-path" class="form-input" maxlength="1000" autocomplete="off" required></label><button type="submit" class="rounded-lg border px-4 py-2">Open path</button><button type="button" id="backup-folder-up" class="rounded-lg border px-4 py-2">Up</button></form>
    <p id="backup-folder-message" class="mb-3 text-sm text-slate-600" role="status" aria-live="polite"></p>
    <div id="backup-folder-list" class="mb-4 space-y-1 rounded-lg border p-2" style="max-height:16rem;overflow:auto" aria-label="Subfolders"></div>
    <label class="block text-sm" for="backup-new-folder">New subfolder (optional)</label><input id="backup-new-folder" class="form-input mb-2" maxlength="100" placeholder="Example: NilambaraBackups" autocomplete="off">
    <p class="mb-4 text-xs text-slate-500">Choose a private writable folder outside the website. A new subfolder is created when the first backup runs.</p>
    <div class="flex flex-wrap justify-end gap-2"><button type="button" id="cancel-backup-picker" class="rounded-lg border px-4 py-2">Cancel</button><button type="button" id="select-backup-folder" class="rounded-lg bg-teal-700 px-4 py-2 text-white">Use this folder</button></div>
</dialog>
<style>#backup-folder-picker::backdrop { background: rgb(15 23 42 / 50%); }</style>
<script>
(() => {
    const dialog = document.getElementById('backup-folder-picker');
    const destination = document.getElementById('backup-destination');
    const change = document.getElementById('change-backup-destination');
    const browse = document.getElementById('browse-backup-folder');
    const pathInput = document.getElementById('backup-folder-path');
    const drives = document.getElementById('backup-folder-drives');
    const list = document.getElementById('backup-folder-list');
    const message = document.getElementById('backup-folder-message');
    const use = document.getElementById('select-backup-folder');
    const up = document.getElementById('backup-folder-up');
    const subfolder = document.getElementById('backup-new-folder');
    let current = null;
    let loading = false;
    let requestNumber = 0;
    change.addEventListener('change', () => { destination.disabled = !change.checked; destination.required = change.checked; });
    destination.required = change.checked;
    const close = () => { dialog.close(); browse.focus(); };
    document.getElementById('close-backup-picker').addEventListener('click', close);
    document.getElementById('cancel-backup-picker').addEventListener('click', close);
    dialog.addEventListener('close', () => browse.focus());
    const updateUse = () => { use.disabled = loading || !current || (!current.selectable && !subfolder.value.trim()); };
    async function load(path = '') {
        const number = ++requestNumber;
        loading = true; current = null; updateUse(); up.disabled = true;
        list.replaceChildren(); message.textContent = 'Opening folder...';
        try {
            const url = new URL(@js(route('backups.folders')), location.origin);
            if (path) url.searchParams.set('path', path);
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            const data = await response.json();
            if (number !== requestNumber || !dialog.open) return;
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || 'Cannot open this folder.');
            current = data; pathInput.value = data.path;
            drives.replaceChildren();
            const blank = document.createElement('option'); blank.value = ''; blank.textContent = 'Choose a drive or home folder'; drives.append(blank);
            for (const root of data.roots) {
                const option = document.createElement('option'); option.value = root.path; option.textContent = root.name; drives.append(option);
            }
            for (const folder of data.folders) {
                const button = document.createElement('button'); button.type = 'button'; button.className = 'block w-full rounded-lg px-3 py-2 text-left text-sm text-teal-700 hover:bg-slate-50'; button.textContent = folder.name; button.addEventListener('click', () => load(folder.path)); list.append(button);
            }
            up.disabled = !data.parent;
            message.textContent = data.truncated ? 'Showing the first 300 folders. Enter a full path to open another folder.' : (data.selectable ? 'This folder can be selected.' : 'Open a writable private folder, or enter a new subfolder name.');
            if (!data.folders.length) { const empty = document.createElement('p'); empty.className = 'p-3 text-sm text-slate-500'; empty.textContent = 'No subfolders in this folder.'; list.append(empty); }
        } catch (error) {
            if (number !== requestNumber || !dialog.open) return;
            message.textContent = error.message || 'Cannot open this folder. You can enter a full path in the backup policy instead.';
        } finally {
            if (number === requestNumber) { loading = false; updateUse(); }
        }
    }
    browse.addEventListener('click', () => { subfolder.value = ''; dialog.showModal(); load(destination.value); });
    document.getElementById('backup-folder-navigation').addEventListener('submit', event => { event.preventDefault(); load(pathInput.value); });
    drives.addEventListener('change', () => { if (drives.value) load(drives.value); });
    up.addEventListener('click', () => { if (current?.parent) load(current.parent); });
    subfolder.addEventListener('input', updateUse);
    use.addEventListener('click', () => {
        if (!current || loading) return;
        const name = subfolder.value.trim();
        if (name && (/^[. ]|[. ]$|[<>:"/\\|?*\x00-\x1f]/.test(name) || name === '.' || name === '..')) {
            message.textContent = 'Enter one folder name without slashes, reserved symbols or leading/trailing dots or spaces.'; subfolder.focus(); return;
        }
        const separator = current.path.includes('\\') ? '\\' : '/';
        destination.value = current.path.replace(/[\\/]+$/, '') + (name ? separator + name : '');
        // Preserve the slash for a drive root selected without a subfolder.
        if (!name) destination.value = current.path;
        change.checked = true; destination.disabled = false; destination.required = true;
        destination.dispatchEvent(new Event('input', { bubbles: true }));
        close(); destination.focus();
    });
})();
</script>
