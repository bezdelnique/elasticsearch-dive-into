# Семпл данных
curl -L https://raw.githubusercontent.com/yiisoft/yii2/master/contrib/completion/bash/yii -o /etc/bash_completion.d/yii


# Конфигурация и маппинг
curl -XPOST "localhost:9200/dict_search" -d'
{
  "settings" : {
    "index" : {
      "creation_date" : "1492181412632",
      "analysis" : {
        "filter" : {
          "ru_stop" : {
            "type" : "stop",
            "stopwords" : "_russian_"
          },
          "ru_stemmer" : {
            "type" : "stemmer",
            "language" : "russian"
          }
        },
        "analyzer" : {
          "default" : {
            "filter" : [ "lowercase", "ru_stop", "ru_stemmer" ],
            "char_filter" : "html_strip",
            "type" : "custom",
            "tokenizer" : "standard"
          }
        }
      },
      "number_of_shards" : "1",
      "number_of_replicas" : "0",
      "uuid" : "W6vDIRvPQIWRBdu9ezG-BQ",
      "version" : {
        "created" : "2040499"
      }
    }
  },
  "mappings" : {
    "words_search" : {
      "properties" : {
        "description" : {
          "type" : "string"
        },
        "dict_id" : {
          "type" : "integer"
        },
        "id" : {
          "type" : "integer"
        },
        "word" : {
          "type" : "string"
        }
      }
    }
  }  
}'


# Проверка что всё получилось
curl -XGET "localhost:9200/dict_search/_settings?pretty"
curl -XGET "localhost:9200/dict_search/_mapping?pretty"


# Массовая вставка. На каждую запись ES возвращает информацию о том что получилось, а что нет
# В примере _id (речь про внутренний _id ES) задан в данных. Если _id не задавать, он будет сгенерирован автоматически
curl -s -H "Content-Type: application/x-ndjson" -XPOST localhost:9200/dict_search/_bulk?pretty --data-binary "@/tmp/elastic-search.json"



# Проверка что данные вставились (поищем)
curl -XGET 'localhost:9200/dict_search/_search?pretty' -H 'Content-Type: application/json' -d'
{
  "query": {
    "match_all": {}
  }
}'


# Проверка что данные вставились (посчитаем)
#
# 400 записей
curl -XGET 'localhost:9200/dict_search/_count?pretty' -H 'Content-Type: application/json' -d'
{
  "query" : {
    "match_all": {}        
  }
}
'


# Пример с аггрегацией. Внимание на size:
# 1. size в теле - это кол-во строчек данных которые надо вернуть ES в запросе
# 2. size внутри group_by_state - это результаты агрегации. Ограничение в 10 результатов действует и здесь. Если ничего не указывать, а уникальных значений dict_id будет больше 10, то мы не увидим всех результатов
#
# В 4 dict_id (key в ответе), по 100 записей в каждом (doc_count в в ответе)
 
curl -XGET 'localhost:9200/dict_search/_search?pretty' -H 'Content-Type: application/json' -d'
{
  "size": 0,
  "aggs": {
    "group_by_state": {
      "terms": {
        "field": "dict_id", 
        "size" : 20
      }
    }
  }
}
'



# То зачем всё затевалось - полнотекстовый поиск
# size задал 10, чтобы не засорять вывод
#
# 1. Нашлось 2 записи в разных словарях
# 2. Результат подсветки в секции highlight
curl -XGET 'localhost:9200/dict_search/_search?pretty' -H 'Content-Type: application/json' -d'
{
  "size" : 100,
  "query" : {
    "multi_match" : {
      "query" : "абордажом",
      "type" : "best_fields",
      "fields" : ["word^1", "description"]
    }
  },
  "highlight" : {
    "fields" : {
      "word" : {},
      "description" : {}
    }
  }
}'




# Удаление данных по запросу
curl -XDELETE 'http://localhost:9200/dict_search/_query' -d '{
    "query" : { 
        "match_all" : {}
    }
}'


# Прибрать за собой (удаляет индекс со всем содержимым)
curl -XDELETE 'localhost:9200/dict_search?pretty'



