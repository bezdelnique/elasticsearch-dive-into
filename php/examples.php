<?php


////// Проверка существования индекса, и проверочные запросы: данные, маппинг, конфигурация
if (DictWordsEsSearch::getInstance()->exists()) {
    $results = DictWordsEsSearch::getInstance()->searchMatchAll();
    $settings = DictWordsEsSearch::getInstance()->getSettings();
    $mapping = DictWordsEsSearch::getInstance()->getMapping();
}


////// Удаление индекса
$response = DictWordsEsSearch::getInstance()->delete();


////// Создание индекса
$response = DictWordsEsSearch::getInstance()->create();


////// Массовая вставка данных
foreach (SomeDataSource() as $i => $data) {
    echo "{$i} <br>\n";

    if (DictWordsEsSearch::getInstance()->bulk($data, $i)) {
        echo ">>>>> Запись данных: {$i}<br>\n";
        flush();
    }
}

// Send the last batch if it exists
if (DictWordsEsSearch::getInstance()->bulkLast()) {
    echo ">>>>> Запись данных: {$i} (добивочка)<br>\n";
    flush();
}


////// Выборка сгруппированных данных
$wordsIndexEsSuggest = [];
if (DictWordsIndexEsSuggest::getInstance()->exists()) {
    foreach (DictWordsIndexEsSuggest::getInstance()->getCountByDict() as $dictId => $cnt) {
        $wordsIndexEsSuggest[$dictId][0] = $cnt;
    }
}





////// Предиктивный поиск
$data = DictWordsIndexEsSuggest::getInstance()->searchSuggest($searchTerm);


////// Полнотекстовый поиск
$hits = DictWordsEsSearch::getInstance()->searchHightlight($searchTerm);





