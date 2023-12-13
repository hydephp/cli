# Internal data branch

This branch tracks public GitHub traffic data, as it is otherwise lost after 14 days.

## Example data

This is an example of the data that is stored in this branch.

Please note that this branch is internal and is not covered by any backwards compatibility guarantees, thus this data format may change at any time.

```json
{
  "_database": {
    "last_updated": 1704063600,
    "content_hash": "a2dee47ba6268925da97750ab742baf67f02e2fb54ce23d499fb66a5b0222903",
    "total_views": 1500,
    "total_clones": 6750
  },
  "traffic": {
    "2024-01-01T00:00:00Z": {
      "views": {
        "count": 1000,
        "uniques": 300
      },
      "clones": {
        "count": 50,
        "uniques": 25
      }
    }
  },
  "popular": {
    "2024-01": {
      "paths": {
        "50d858e0985ecc7f60418aaf0cc5ab587f42c2570a884095a9e8ccacd0f6545c": {
          "path": "\/hydephp\/cli",
          "title": "hydephp\/cli: Experimental global Hyde binary project",
          "count": 100,
          "uniques": 10
        }
      },
      "referrers": {
        "github.com": {
          "count": 50,
          "uniques": 20
        }
      }
    }
  }
}
```

## Abstract schema

This is an abstract representation of the data format.

```php
$database = array{
  '_database' => array{
    'last_updated' => int,
    'content_hash' => string,
    'total_views' => int,
    'total_clones' => int
  },
  'traffic' => array{
    string<timestamp('YYYY-MM-DDTHH:MM:SSZ')> => array{
      'views' => array{
        'count' => int,
        'uniques' => int
      },
      'clones' => array{
        'count' => int,
        'uniques' => int
      }
    }
  },
  'popular' => array{
    string<timestamp('YYYY-MM')> => array{
      'paths' => array{
        string<sha256($path)> => array{
          'path' => string,
          'title' => string,
          'count' => int,
          'uniques' => int
        }
      },
      'referrers' => array{
        string<domain> => array{
          'count' => int,
          'uniques' => int
        }
      }
    }
  }
}
```
