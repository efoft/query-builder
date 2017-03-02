# QueryBuilder: SQL
===
The package allows to produce native SQL queries making multiple and chanable calls on class object. SQL queries are injection-protected with substitutors.
Also there is the method to get all binded values for such substitutors.

### Installation
------
The package is intended to be used via Composer. It currently is not on Packagist, so add this repository description to you composer.json:
```
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/efoft/query-builder"
        },
    ]
```
and require it:
```
    "require": {
        "efoft/query-builder" : "dev-master"
    }
```

### Initialization
------

```
$q = new QueryBuilder\SQLQueryBuilder();
```
There are 2 optional argument for class construct:
* $quoting - type of quoting: one of 'none','sql','mysql','mssql', 'sqlite'. See http://www.sqlite.org/lang_keywords.html for explanation.
* $debug - true of false
With specified quoting type of mysql and enabled debug mode:
```
$q = new QueryBuilder\SQLQueryBuilder('mysql', true);
```

### Usage
------
You can call methods on created $q object one by one in a chain way to fill all required parameters to generate a query.
Important! If you reuse $q for new query, first call ->newQuery() method.

It's possible to run the following methods multiple times, the parameters will be aggregated. It's useful if some query changes are conditional.
  * table / from
  * select
  * insert
  * update
  * order
  * group
  * where

Methods table() and from() are just aliases. Specifiing multiple table is worth only for SELECT queries. For all the rest only first table will be used.
All above methods except from where() can accept arguments in several possible ways:
   *  'item1','item2'...
   *  'item1, item2...'
   *   array('item1','item2'...)
   *   and mixes of the above.
   
Method where() specifies WHERE conditions via array. The format is similar to MongoDB find() syntax, but so far limited
with $or and $and operators.
   
Examples below illustrate it in more details.

#### Select
Method ->select(). Can chain and can be called multiple times. Duplicates are automatically removed.
For specifying aliases use associative arrays: array('field'=>'alias', ...).
The following methods allow to set ORDER BY, GROUP BY and LIMIT
  * order()
  * group()
  * limit()

```
// select
$q->from('table1,  table2 ')->from('table2')->select(array('table2.age'=>'a','table1.name'=>'n'))->where(array('id'=>13));
$q->where(array('age'=>'/3.*/'))->order('name')->limit(100);
echo $q->getQuery() . PHP_EOL;
echo print_r($q->getBindings()) . PHP_EOL;

SELECT `table2`.`age` AS a,`table1`.`name` AS n FROM table1,table2 WHERE `id`=:id AND `age` LIKE :age ORDER BY name LIMIT 100;
Array
(
    [id] => 13
    [age] => 3%
)
```

#### Insert
Method ->insert(). Can chain and can be called multiple times.
Arguments must be associative arrays like array('field'=>'value'...).

```
// insert
$q->newQuery()->insert(array('age'=>34, 'name'=>'John'))->table('table1,table2');
echo $q->getQuery() . PHP_EOL;
echo print_r($q->getBindings()) . PHP_EOL;

INSERT INTO table1(`age`,`name`) VALUES (:age,:name);
Array
(
    [age] => 34
    [name] => John
)
```

#### Update
Method ->update(). Can chain and can be called multiple times.
Arguments must be associative arrays like array('field'=>'value'...).

```
// update
$q->newQuery()->update(array('age'=>34, 'name'=>'John'))->table('table1')->where(array('$or'=>array('id'=>13, 'phone'=>'/+7916.*/')));
echo $q->getQuery() . PHP_EOL;
echo print_r($q->getBindings()) . PHP_EOL;

UPDATE table1 SET `age`=:age,`name`=:name WHERE `id`=:id OR `phone` LIKE :phone;
Array
(
    [age] => 34
    [name] => John
    [id] => 13
    [phone] => +7916%
)
```

#### Delete
Method ->delete(). Does not require any arguments, but the conditions must follow with where().

```
// delete
$q->newQuery()->delete()->from('table3')->where(array('id'=>13));
echo $q->getQuery() . PHP_EOL;
echo print_r($q->getBindings()) . PHP_EOL;

DELETE FROM table3 WHERE `id`=:id;
Array
(
    [id] => 13
)
```

#### Retrieve results
To get resulted query:
```
$sql = $q->getQuery();
```

To get binded values:
```
$values = $q->getBindings()'
```