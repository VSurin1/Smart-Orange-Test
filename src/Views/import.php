<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Імпорт заявок</title>
    <link rel="stylesheet" href="/css/import.css">
</head>
<body>
<main>
    <section class="card">
        <label class="drop" id="drop" for="file">
            <strong>Оберіть файл або перетягніть його сюди</strong>
            <span>CSV · до 100 МБ</span>
        </label>
        <input id="file" type="file" accept=".csv,text/csv">
        <div class="file" id="filename">Файл не вибрано</div>
        <button id="start" type="button" disabled>Почати імпорт</button>
        <div class="progress" aria-live="polite">
            <div class="row"><span id="stage">Очікування файлу</span><strong id="value">0%</strong></div>
            <div class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="bar"><div id="fill"></div></div>
            <p class="status" id="status"></p>
        </div>
    </section>
</main>
<script src="/js/import.js" defer></script>
</body>
</html>
