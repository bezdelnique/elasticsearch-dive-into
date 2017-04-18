# Семпл данных
curl -L https://raw.githubusercontent.com/bezdelnique/elasticsearch-dive-into/master/elastic-suggest.json -o /tmp/elastic-search.json


# Конфигурация и маппинг
curl -XPOST "localhost:9200/dict_suggest" -d'
{
  "settings" : {
    "index" : {
      "analysis" : {
        "filter" : {
          "ru_stop" : {
            "type" : "stop",
            "stopwords" : "_russian_"
          },
          "ru_stemmer" : {
            "type" : "stemmer",
            "language" : "russian"
          },
          "autocomplete_filter" : {
            "type" : "edge_ngram",
            "min_gram" : "3",
            "max_gram" : "20"
          }
        },
        "analyzer" : {
          "autocomplete" : {
            "filter" : [ "lowercase", "autocomplete_filter" ],
            "type" : "custom",
            "tokenizer" : "standard"
          },
          "my_fulltext" : {
            "filter" : [ "lowercase", "ru_stop", "ru_stemmer" ],
            "char_filter" : "html_strip",
            "type" : "custom",
            "tokenizer" : "standard"
          }
        }
      }
    }
  },
  "mappings" : {
    "words_suggest" : {
      "properties" : {
        "dict_id" : {
          "type" : "integer"
        },
        "id" : {
          "type" : "integer"
        },
        "word" : {
          "type" : "string",
          "fields" : {
            "fulltext" : {
              "type" : "string",
              "analyzer" : "my_fulltext"
            },
            "raw" : {
              "type" : "string",
              "boost" : 2.0,
              "index" : "not_analyzed",
              "norms" : {
                "enabled" : true
              }
            }
          },
          "analyzer" : "autocomplete"
        }
      }
    }
  }
}'


# Проверка что всё получилось
curl -XGET "localhost:9200/dict_suggest/_settings?pretty"
curl -XGET "localhost:9200/dict_suggest/_mapping?pretty"


# Массовая вставка. На каждую запись ES возвращает информацию о том что получилось, а что нет
# _id генерируется автоматически
curl -s -H "Content-Type: application/x-ndjson" -XPOST localhost:9200/dict_suggest/_bulk?pretty --data-binary "@/tmp/elastic-suggest.json"



# Проверка что данные вставились (поищем)
curl -XGET 'localhost:9200/dict_suggest/_search?pretty' -H 'Content-Type: application/json' -d'
{
  "query": {
    "match_all": {}
  }
}'


# Проверка что данные вставились (посчитаем)
#
# 400 записей
curl -XGET 'localhost:9200/dict_suggest/_count?pretty' -H 'Content-Type: application/json' -d'
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
 
curl -XGET 'localhost:9200/dict_suggest/_search?pretty' -H 'Content-Type: application/json' -d'
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



# То зачем всё затевалось - предиктивный поиск
# size задал 15, чтобы не засорять вывод
curl -XGET 'localhost:9200/dict_suggest/_search?pretty' -H 'Content-Type: application/json' -d'
{
    "size" : 15,
    "query" : {
        "multi_match" : {
            "query" : "абар",
            "type" : "best_fields",
            "fields" : ["word.raw^2", "word.fulltext", "word"]
        }
    },
    "sort" : {
        "_score" : "desc",
        "word.raw" : "asc"
    },
    "highlight" : {
        "fields" : {
            "word" : {}
        }
    }
    
}'


# Удаление данных по запросу: только словари с id = 20
curl -XDELETE 'http://localhost:9200/dict_suggest/_query' -d '{
    "query" : {
        "term" : { "dict_id" : "20" }
    }
}'



# Удаление данных по запросу: все данные
curl -XDELETE 'http://localhost:9200/dict_suggest/_query' -d '{
    "query" : { 
        "match_all" : {}
    }
}'


# Прибрать за собой (удаляет индекс со всем содержимым)
curl -XDELETE 'localhost:9200/dict_suggest?pretty'



