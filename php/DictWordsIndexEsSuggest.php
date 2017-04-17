<?php
namespace app\models;

use Elasticsearch\ClientBuilder;
use app\models\DictEsBase;


class DictWordsIndexEsSuggest extends DictEsBase
{
    static function index()
    {
        return 'dict';
    }

    static function type()
    {
        return 'words';
    }

    protected function getConfig()
    {
        $params = [
            'index' => static::index(),
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'analysis' => [
                        'filter' => [
                            "ru_stop" => [
                                "type" => "stop",
                                "stopwords" => "_russian_"
                            ],
                            "ru_stemmer" => [
                                "type" => "stemmer",
                                "language" => "russian"
                            ],
                            'autocomplete_filter' => [
                                'type' => 'edge_ngram',
                                'min_gram' => 3,
                                'max_gram' => 20,
                            ]
                        ],
                        'analyzer' => [
                            'my_fulltext' => [
                                'char_filter' => 'html_strip',
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => [
                                    'lowercase',
                                    // 'russian_morphology',
                                    // 'english_morphology',
                                    'ru_stop',
                                    'ru_stemmer'
                                ]
                            ],
                            'autocomplete' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => [
                                    0 => 'lowercase',
                                    1 => 'autocomplete_filter',
                                ]
                            ]
                        ]
                    ]
                ],
                'mappings' => [
                    static::type() => [
                        'properties' => [
                            'id' => [
                                'type' => 'integer'
                            ],
                            'word_id' => [
                                'type' => 'integer',
                            ],
                            'dict_id' => [
                                'type' => 'integer'
                            ],
                            'word' => [
                                'type' => 'string',
                                'analyzer' => 'autocomplete',
                                "fields" => [
                                    "raw" => [
                                        "type" => "string",
                                        "index" => "not_analyzed",
                                        'doc_values' => true,
                                        // 'boost' => '2.0',
                                    ],
                                    "fulltext" => [
                                        'type' => 'string',
                                        'analyzer' => 'my_fulltext',
                                        // 'boost' => '1.0'
                                    ],
                                ],
                            ],
                        ]
                    ]
                ]
            ]
        ];

        return $params;
    }


    /**
     * @param $searchTerm
     * @return array возвращает массив идентификаторов WordIndex
     */
    public function searchSuggest($searchTerm)
    {
        $params = [
            'index' => self::index(),
            'body' => [
                'track_scores' => true,
                'size' => 15,
                'query' => [
                    'multi_match' => [
                        'query' => $searchTerm,
                        "type" => "best_fields",
                        // "tie_breaker" => 0.3,
                        // "minimum_should_match" => "30%",
                        'fields' => ['word.raw^2', 'word.fulltext', 'word']
                    ]
                ],
                'sort' => [
                    '_score' => 'desc',
                    'word.raw' => 'asc',
                ],
                'highlight' => [
                    'fields' => [
                        'word' => new \stdClass()
                    ]
                ]
            ]
        ];

        $results = $this->getClient()->search($params);
        \Yii::info(['params' => $params, 'results' => $results], 'elasticsearch-suggest');

        // Получение id для выборки из БД индекса
        $arr = [];
        if (isset($results['hits']['hits'])) {
            $arr = [];
            foreach ($results['hits']['hits'] as $row) {
                $arr[] = $row['_source'];
            }
        }

        return $arr;
    }


    public function getCountByDict()
    {
        $params = [
            'index' => static::index(),
            'body' => [
                'size' => 0,
                'aggs' => [
                    'group_by_state' => [
                        'terms' => [
                            'field' => 'dict_id',
                            'size' => 1000
                        ]
                    ]
                ]
            ]
        ];


        $results = $this->getClient()->search($params);

        // Перепаковка аггрегатов
        $arr = [];
        if (isset($results['aggregations']['group_by_state']['buckets'])) {
            foreach ($results['aggregations']['group_by_state']['buckets'] as $bucket) {
                $arr[$bucket['key']] = $bucket['doc_count'];
            }
        }


        return $arr;
    }
}
