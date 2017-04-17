<?php
namespace app\models;

use app\helpers\WordsHelper;
use Elasticsearch\ClientBuilder;
use app\models\DictEsBase;


class DictWordsEsSearch extends DictEsBase
{
    /**
     * @return DictWordsEsSearch
     */
    static public function getInstance()
    {
        if (is_null(self::$_instance)) {
            static::$_instance = new static();
        }

        return self::$_instance;
    }


    static function index()
    {
        return 'dict_search';
    }


    static function type()
    {
        return 'words_search';
    }


    protected function getPkData($arr)
    {
        return $arr['dict_id'] . '_' . $arr['id'];
    }


    protected function getConfig()
    {
        $params = [
            'index' => static::index(),
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'analysis' => [
                        "filter" => [
                            "ru_stop" => [
                                "type" => "stop",
                                "stopwords" => "_russian_"
                            ],
                            "ru_stemmer" => [
                                "type" => "stemmer",
                                "language" => "russian"
                            ],
                        ],
                        'analyzer' => [
                            'default' => [
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
                            'dict_id' => [
                                'type' => 'integer'
                            ],
                            'word' => [
                                'type' => 'string'
                            ],
                            'description' => [
                                'type' => 'string'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return $params;
    }


    public function searchHightlight($searchTerm)
    {
        $params = [
            'index' => static::index(),
            'type' => static::type(),
            'body' => [
                'size' => 100,
                'query' => [
                    'multi_match' => [
                        'query' => $searchTerm,
                        "type" => "best_fields",
                        // "tie_breaker" => 0.3,
                        // "minimum_should_match" => "30%",
                        'fields' => ['word^1', 'description']
                    ]
                ],
                'highlight' => [
                    'fields' => [
                        'description' => new \stdClass(),
                        'word' => new \stdClass()
                    ]
                ]
            ]
        ];

        $results = $this->getClient()->search($params);
        \Yii::info(['params' => $params, 'results' => $results], 'elasticsearch-search');

        // Словарики
        $dict = DictCatalog::findFull()->indexBy('id')->all();


        // Перепаковка результатов поиска
        $arr = [0 => [], 1 => []];
        if (isset($results['hits']['hits'])) {
            foreach ($results['hits']['hits'] as $i => $hit) {
                // descriptionHl
                $t = [];
                if (!empty($hit['highlight']['description'])) {
                    foreach ($hit['highlight']['description'] as $item) {
                        $item = preg_replace("~(<p>|</p>)~", "", $item);
                        $item = preg_replace("~\n+~", "", $item);
                        $t[] = $item;
                    }
                    $descriptionHl = "<p>" . join("</p><p>", $t) . "</p>";
                } else {
                    $descriptionHl = WordsHelper::generateDescriptionPreview($hit['_source']['description']);
                }


                // wordHl
                $t = [];
                if (!empty($hit['highlight']['word'])) {
                    foreach ($hit['highlight']['word'] as $item) {
                        $item = preg_replace("~(<p>|</p>)~", "", $item);
                        $item = preg_replace("~\n+~", "", $item);
                        $t[] = $item;
                    }
                    $wordHl = join(" ", $t);
                } else {
                    $wordHl = $hit['_source']['word'];
                }

                $dictId = $hit['_source']['dict_id'];
                $word = $hit['_source']['word'];
                $description = $hit['_source']['description'];

                $arr[$dict[$dictId]->theme_lang][] = [
                    'id' => $hit['_source']['id'],
                    'word' => $word,
                    'word_hl' => $wordHl,
                    'word_url' => WordsHelper::generateUrl($dict[$dictId]->nick, $word),
                    'description' => $description,
                    'description_hl' => $descriptionHl,
                    'dict_id' => $dictId,
                    'dict_name' => $dict[$dictId]->name,
                    'dict_dname' => $dict[$dictId]->dname,
                    'dict_nick' => $dict[$dictId]->nick,
                    'dict_url' => $dict[$dictId]->getUrl(),
                ];
            }
        }


        return $arr;
    }
}
