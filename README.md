## Nrich\MySql
A simple MySql class.


### How to

init the database connection on construct

    $sql = new Nrich\Mysql(
        'localhost',
        'root',
        'my-password',
        'my-database'
    );
    
    
###### quering
    
the mysql class escape every param.

    $sql->query("SELECT * FROM my_table WHERE id = :id", [
        'id' => 77
    ]);
    
###### count the result.

    if($sql->count()) ...


###### fetching (one)

    $result = $sql->fetchOne(); 
    $result = $sql->fetchOne(true); // optional as array 

###### fetching (multiple)

    $results = $sql->fetchAll();
    $results = $sql->fetchAll(true); // optional as nested array
    

#### Helpers

##### Raw
if you wanna pass a raw param the your mysql query use the static raw method.

    $sql->query("SELECT * FROM my_table WHERE birthday < :DATE", [
        $sql::raw('NOW()'),
    ]);
    

##### Implode
if you need an array in your query you can use the implode helper to implode the data

    $sql->query("SELECT * FROM my_table WHERE id IN (:MY_IDS)", [
        'MY_IDS' => $sql::implode([1,2,3,4], ',')
    ]);
    
each param of array will get an own unique key an escaped like all other params..





    
