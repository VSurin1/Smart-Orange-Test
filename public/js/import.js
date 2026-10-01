(() => {
    const input = document.querySelector('#file');
    const drop = document.querySelector('#drop');
    const start = document.querySelector('#start');
    const filename = document.querySelector('#filename');
    const stage = document.querySelector('#stage');
    const value = document.querySelector('#value');
    const bar = document.querySelector('#bar');
    const fill = document.querySelector('#fill');
    const status = document.querySelector('#status');
    const uploadSize = 1024 * 1024;
    let file = null;

    function progress(label, percent, message = '') {
        stage.textContent = label;
        value.textContent = Math.round(percent) + '%';
        fill.style.width = Math.min(100, percent) + '%';
        bar.setAttribute('aria-valuenow', String(Math.round(percent)));
        status.textContent = message;
    }

    async function request(path, body, headers = {}) {
        const response = await fetch(path, { method: 'POST', body, headers });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Помилка сервера');
        return result;
    }

    function form(data) {
        const body = new FormData();
        for (const [key, val] of Object.entries(data)) body.append(key, val);
        return body;
    }

    function choose(chosen) {
        file = chosen || null;
        filename.textContent = file ? `${file.name} · ${(file.size / 1048576).toFixed(1)} МБ` : 'Файл не вибрано';
        start.disabled = !file;
        status.className = 'status';
        progress('Очікування файлу', 0);
    }

    input.addEventListener('change', () => choose(input.files[0]));
    for (const event of ['dragenter', 'dragover']) drop.addEventListener(event, e => {
        e.preventDefault(); drop.classList.add('over');
    });
    for (const event of ['dragleave', 'drop']) drop.addEventListener(event, e => {
        e.preventDefault(); drop.classList.remove('over');
    });
    drop.addEventListener('drop', e => choose(e.dataTransfer.files[0]));

    start.addEventListener('click', async () => {
        if (!file) return;
        start.disabled = true;
        status.className = 'status';
        try {
            const job = await request('/api/imports/start', form({ filename: file.name, size: file.size }));
            let offset = 0;
            while (offset < file.size) {
                const chunk = file.slice(offset, offset + uploadSize);
                const result = await request('/api/imports/chunk', chunk, {
                    'Content-Type': 'application/octet-stream',
                    'X-Import-Id': job.id,
                    'X-Upload-Offset': String(offset),
                });
                offset = result.uploaded_bytes;
                progress('Завантаження файлу', offset / file.size * 100);
            }
            await request('/api/imports/finish', form({ id: job.id }));
            progress('Запис у базу', 0, 'Оброблено 0 рядків');
            let result;
            do {
                result = await request('/api/imports/process', form({ id: job.id }));
                progress('Запис у базу', result.progress_percent,
                    `Оброблено ${result.imported_rows.toLocaleString('uk-UA')} рядків`);
            } while (result.status !== 'completed');
            stage.textContent = 'Імпорт завершено';
            status.className = 'status success';
        } catch (error) {
            status.className = 'status error';
            status.textContent = error.message;
            start.disabled = false;
        }
    });
})();
