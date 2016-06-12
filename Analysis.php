<?php
use Luracast\Restler\RestException;

class Analysis
{
  private function processEMA(&$arrayList, $number)
  {  
    $arrayCount = count($arrayList);
    if ($arrayCount > $number) {
      $sumPrice = 0;
      for (i=0; i<$number; i++) {
        $sumPrice = $sumPrice + $arrayList[i]['price'];
      }
      $initSMA = $sumPrice/$number;
      
      $yesterdayEMA = $initSMA;
      for (i=$number; i<$arrayCount; i++) {
        
      }
    }
  }

  /**
   * @smart-auto-routing false
   * @url GET processday
   */ 
	public function getDayLoad() 
	{
    $statement = "SELECT * FROM stock";
    $stockList = \Db::getResult($statement);

    ini_set('max_execution_time', 300); //300 seconds = 5 minutes
    foreach ($stockList as $stock) {
      $statement = "
        SELECT * FROM stock_history
        WHERE ticker = :ticker
        AND unit = :unit
        ORDER BY date
      ";
      $bind = array(
        'ticker' => $stock['ticker']
       ,'unit'   => 'd'
      );
      $priceList = \Db::getResult($statement, $bind);
      
      processEMA($priceList, 12);
      processEMA($priceList, 26);
      processEMA($priceList, 9);
      
    return $response;
  }
  
}