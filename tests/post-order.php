<?php
require __DIR__.'/../app/post_management.php';
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('CREATE TABLE post(id INTEGER PRIMARY KEY,name TEXT);CREATE TABLE app_setting(key TEXT PRIMARY KEY,value TEXT,label TEXT);INSERT INTO post VALUES(1,"A"),(2,"B"),(3,"C")');
save_recruitment_post_order($pdo,[2,1,3]);$rows=$pdo->query('SELECT * FROM post')->fetchAll();if(array_column(ordered_recruitment_posts($pdo,$rows),'id')!==[2,1,3])throw new Exception('Order mismatch');
foreach([[1,2],[1,1,3],[1,2,99]] as $bad){try{save_recruitment_post_order($pdo,$bad);throw new Exception('Invalid order accepted');}catch(RuntimeException $e){}}
$pdo->exec('INSERT INTO post VALUES(4,"D")');if(array_column(ordered_recruitment_posts($pdo,$pdo->query('SELECT * FROM post')->fetchAll()),'id')!==[2,1,3,4])throw new Exception('New post handling failed');
echo "PASS: saved post ordering, invalid order rejection, new post append\n";
