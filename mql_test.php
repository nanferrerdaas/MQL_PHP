<?php  

require 'mql.php';

$connection = new MongoClient();

$mql = new MQL($connection, "Mql");

//  $mql->query("select npi, fname from physician where lname =  'Smith'");

//  print_r($mql->result);

//  $mql->query("select  * from physician where npi<=222222222");

// print_r($mql->result);

$mql->query("select  npi, fname, amount, reason from physician , payments join on npi=npi where  npi = 987654321");

print_r($mql->result);

?>
