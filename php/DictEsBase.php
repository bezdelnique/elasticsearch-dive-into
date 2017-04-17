<?php
namespace app\models;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Elasticsearch\ClientBuilder;
use app\models\DictExceptionEs;

class DictEsBase
{
    /**
     * @var \Elasticsearch\Client
     */
    protected $_client;
    protected $_bulkChunkSize = 1000;
    protected $_bulkParams = [];
    /**
     * Кеш аттрибутов
     * Строится по конфигурации маппера
     */
    protected $_attributes = [];


    protected static $_instance = null;

    protected function __construct()
    {
        // $logger = new Logger('name');
        // $logger->pushHandler(new StreamHandler(\Yii::getAlias('@app/runtime/log/elasticsearch-search.log'), Logger::API));
        // $this->_client = ClientBuilder::create()->setLogger($logger)->build();
        $this->_client = ClientBuilder::create()->build();

        /**
         * Кэш аттрибутов
         */
        if (empty($this->_attributes)) {
            $config = $this->getConfig();
            foreach ($config['body']['mappings'][static::type()]['properties'] as $attr => $arr) {
                $this->_attributes[] = $attr;
            }
        }
    }

    protected function __clone()
    {
        // ограничивает клонирование объекта
    }

    static public function getInstance()
    {
        if (is_null(self::$_instance)) {
            static::$_instance = new static();
        }

        return self::$_instance;
    }


    static function index()
    {
        throw new DictExceptionEs("Нужно задать index");
    }

    static function type()
    {
        throw new DictExceptionEs("Нужно задать type");
    }


    protected function checkAttributes($arr)
    {
        $diff = array_diff(array_keys($arr), $this->_attributes);
        if (!empty($diff)) {
            throw new DictExceptionEs("Аттриббуты импортируемых данных не совпадают: " . join(", ", $diff));
        }
    }


    protected function getConfig()
    {
        throw new DictExceptionEs("Нужно задать конфигурацию");
    }


    /**
     * @return \Elasticsearch\Client
     */
    public function getClient()
    {
        return $this->_client;
    }


    public function exists()
    {
        return $this->getClient()->indices()->exists(['index' => $this->index()]);
    }


    public function create()
    {
        return $this->getClient()->indices()->create($this->getConfig());
    }


    public function delete()
    {
        return $this->getClient()->indices()->delete(['index' => static::index()]);
    }


    public function getSettings()
    {
        return $this->getClient()->indices()->getSettings(['index' => static::index()]);
    }


    public function getMapping()
    {
        return $this->getClient()->indices()->getMapping(['index' => static::index()]);
    }


    /**
     * Возможность формировать _id из произвольного набора полей
     * @param $arr
     */
    protected function getPkData($arr)
    {
        return $arr['id'];
    }


    /**
     * Нужно вызывать bulk() внутри цикла, а за его пределами bulkLast(),
     * чтобы не потерять последнюю порцию данных
     *
     *
     * @param $word
     * @param $iterator магия: если значение 1, то обнуляем параметры групповой вставки
     *
     * @return bool возвращает true когда делает вставку. В противном случае null.
     */
    public function bulk($word, $iterator)
    {
    	/**
    	 * Проверка что передаваемый массив содержит необходимые данные
    	 */
        $this->checkAttributes($word);

        /**
         * Немного магии
         */
        if ($iterator == 1) {
            $this->_bulkParams = ['body' => []];
        }

        $this->_bulkParams['body'][] = [
            'index' => [
                '_index' => static::index(),
                '_type' => static::type(),
                '_id' => $this->getPkData($word)
            ]
        ];

        // $params['body'][] = \yii\helpers\ArrayHelper::toArray($word);
        $this->_bulkParams['body'][] = $word;


        // Every [_bulkChunkSize] documents stop and send the bulk request
        if ($iterator % $this->_bulkChunkSize == 0) {
            $responses = $this->getClient()->bulk($this->_bulkParams);

            // erase the old bulk request
            $this->_bulkParams = ['body' => []];

            // unset the bulk response when you are done to save memory
            unset($responses);

            return true;
        }

        return null;
    }


    public function bulkLast()
    {
        if (!empty($this->_bulkParams['body'])) {
            $responses = $this->getClient()->bulk($this->_bulkParams);

            // erase the old bulk request
            $this->_bulkParams = ['body' => []];

            // unset the bulk response when you are done to save memory
            unset($responses);

            return true;
        }

        return null;
    }


    public function setBulkChunkSize($bulkChunkSize)
    {
        $this->_bulkChunkSize = $bulkChunkSize;
    }


    /**
     * ********************************************************************
     * Пользовательские функции                                           *
     * ********************************************************************
     */


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


    public function searchMatchAll()
    {
        $params = [
            "index" => static::index(),
            "body" => [
                "query" => [
                    "match_all" => new \stdClass()
                ]
            ]
        ];

        return $this->getClient()->search($params);
    }
}
