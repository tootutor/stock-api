<?php
use Luracast\Restler\RestException;
use DirkOlbrich\YahooFinanceQuery\YahooFinanceQuery;

class Analysis
{
  /**
   * @smart-auto-routing false
   * @url GET processday
   */ 
	public function getDayLoad() 
	{
    $query = new YahooFinanceQuery;
    $today = date('Y-m-d');
    
    $statement = "SELECT * FROM stock";
    $stockList = \Db::getResult($statement);

    ini_set('max_execution_time', 300); //300 seconds = 5 minutes
    $response = array();
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
      if ($stock['lastUpdatePriceEOD'] == '0000-00-00') {
        $startDate = '1900-01-01';
      } else {
        $date = date_create($stock['lastUpdatePriceEOD']);
        date_add($date, date_interval_create_from_date_string('1 days'));
        $startDate = date_format($date, 'Y-m-d');
      }
      $endDate = $today;
      $unit = 'd';
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
      }
      $response[$stock['ticker']] = $count;
      
      $statement = "
        UPDATE stock
        SET lastUpdatePriceEOD = :today
        WHERE ticker = :ticker
      ";
      $bind = array(
        'ticker' => $stock['ticker']
       ,'today'  => $today
      );
      $row_execute = \Db::execute($statement, $bind);
      
    }
    return $response;
  }
  
}