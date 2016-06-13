<?php
use Luracast\Restler\RestException;
use DirkOlbrich\YahooFinanceQuery\YahooFinanceQuery;

class History
{
  /**
   * @smart-auto-routing false
   * @url POST loadprice
   */ 
	public function postLoadPrice($unit = 'd', $limit = 50) 
	{
    $query = new YahooFinanceQuery;
    $today = date('Y-m-d');
    
    $statement = "
      SELECT S.*, L.lastDate
      FROM stock AS S
      INNER JOIN stock_update_log AS L
      ON L.ticker = S.ticker
      AND L.unit = :unit
      WHERE L.lastUpdateDate < :today 
    ";
    if ($limit > 0) {
      $statement = $statement . ' LIMIT ' . $limit;
    }
    $bind = array(
      'today' => $today
     ,'unit'  => $unit 
    );
    $stockList = \Db::getResult($statement, $bind);

    ini_set('max_execution_time', 300); //300 seconds = 5 minutes
    $response = array();
    foreach ($stockList as $stock) {
      if ($stock['lastDate']) {
        $date = date_create($stock['lastDate']);
        date_add($date, date_interval_create_from_date_string('1 days'));
        $startDate = date_format($date, 'Y-m-d');
      } else {
        $startDate = '1900-01-01';
      }
      $endDate = $today;
      $dataList = $query->historicalQuote($stock['symbol'], $startDate, $endDate, $unit)->get();
      $count = 0;
      foreach($dataList as $data) {
        $statement = "
          INSERT INTO stock_history (ticker, unit, date, open, close, high, low, volume, adjclose)
          values (:ticker, :unit, :date, :open, :close, :high, :low, :volume, :adjclose)
        ";
        $bind = array(
          'ticker'   => $stock['ticker']
         ,'unit'     => $unit
         ,'date'     => $data['Date']
         ,'open'     => $data['Open']
         ,'close'    => $data['Close']
         ,'high'     => $data['High']
         ,'low'      => $data['Low']
         ,'volume'   => $data['Volume']
         ,'adjclose' => $data['AdjClose']
        );
        $row_insert = \Db::execute($statement, $bind);
        $count = $count + $row_insert;
        $lastDate = $data['Date'];
      }
      $response[$stock['ticker']] = $count;
      
      if ($count > 0) {
        $statement = "
          UPDATE stock_update_log
          SET lastUpdateDate = :lastUpdateDate
             ,lastDate       = :lastDate
          WHERE ticker = :ticker
          AND   unit   = :unit
        ";
        $bind = array(
           'ticker'         => $stock['ticker']
          ,'unit'           => $unit
          ,'lastUpdateDate' => $today
          ,'lastDate'       => $lastDate
        );
        $row_execute = \Db::execute($statement, $bind);
        
        if ($row_execute = 0) {
          $statement = "
            INSERT INTO stock_update_log (ticker, unit, lastDate, lastUpdateDate)
            VALUES (:ticker, :unit, :lastDate, :lastUpdateDate)
          ";
          $row_execute = \Db::execute($statement, $bind);
        }
      }
    }
    
    return $response;
  }
  
}