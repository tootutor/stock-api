<?php
use Luracast\Restler\RestException;
use DirkOlbrich\YahooFinanceQuery\YahooFinanceQuery;

class Stock
{
  /**
   * @smart-auto-routing false
   * @url GET localstock
   */ 
	public function getLocalStock() 
	{
  	$statement = 'SELECT * FROM stock';
		return \Db::getResult($statement);
	}
  
  /**
   * @smart-auto-routing false
   * @url POST loadinfo
   */ 
	public function postLoadInfo($limit = 0) 
	{
    $query = new YahooFinanceQuery;

    $statement = "SELECT * FROM stock";
    if ($limit > 0) {
      $statement = $statement . ' LIMIT ' . $limit;
    }
    $stockList = \Db::getResult($statement);

    ini_set('max_execution_time', 300); //300 seconds = 5 minutes
    $count = 0;
    foreach ($stockList as $stock) {
      $string = $stock['symbol'];
      $data = $query->symbolSuggest($string)->get();
      $statement = "
        UPDATE stock
        SET name     = :name
          , exch     = :exch
          , exchDisp = :exchDisp
          , type     = :type
          , typeDisp = :typeDisp
        WHERE ticker = :ticker
      ";
      $bind = array(
        'ticker'   => $stock['ticker']
       ,'name'     => $data[0]['name']
       ,'exch'     => $data[0]['exch']
       ,'exchDisp' => $data[0]['exchDisp']
       ,'type'     => $data[0]['type']
       ,'typeDisp' => $data[0]['typeDisp']
      );
      $row_update = \Db::execute($statement, $bind);
      $count = $count + $row_update;
    }
    $response = new \stdClass();
    $response->count = $count;
    return $response;
  }
  
}