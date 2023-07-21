<?

//require_once("vendor/ChefServerApi/ChefServerApi.class.php");

//$api = new ChefServer("mskchefserver",'443','rdt', '/etc/chef/rdt.pem');
//var_dump( $api->get('/clients') );
// getting node list
//var_dump( $api->get('/nodes/mskchefclient') );
//var_dump( $api->get('/cookbooks/glap') );
// fetch 'test' data bag
//var_dump( $api->get('/data/test') );
// create 'test' data bag
//$api->post('/data', array('name'=>'test'));
// insert into 'test' data bag
//$api->post('/data/test', array('id'=>'test_id','somekey'=>'somevalue'));
// update a data bag item
//$api->put('/data/test', 'test_id', array('id'=>'bla','somekey'=>'somenewvalue', 'newcol'=>'newvalue')) ;
// delete a data bag item
//$api->delete('/data/test', 'test_id');