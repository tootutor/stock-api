<?php
use Luracast\Restler\RestException;
use DirkOlbrich\YahooFinanceQuery\YahooFinanceQuery;

class History
{
  /**
   * @smart-auto-routing false
   * @url GET dayload
   */ 
	public function getDayLoad() 
	{
    $query = new YahooFinanceQuery;
    $today = date('Y-m-d');
    
    $statement = "SELECT * FROM stock WHERE lastUpdatePriceEOD < :today LIMIT 50";
    $bind = array('today' => $today);
    $stockList = \Db::getResult($statement, $bind);

    ini_set('max_execution_time', 300); //300 seconds = 5 minutes
    $response = array();
    foreach ($stockList as $stock) {
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

  /**
   * @smart-auto-routing false
   * @url GET weekload
   */ 
	public function getWeekLoad() 
	{
    $query = new YahooFinanceQuery;
    $today = date('Y-m-d');
    
    $statement = "SELECT * FROM stock WHERE lastUpdatePriceEOW < :today LIMIT 50";
    $bind = array('today' => $today);
    $stockList = \Db::getResult($statement, $bind);

    ini_set('max_execution_time', 300); //300 seconds = 5 minutes
    $response = array();
    foreach ($stockList as $stock) {
      if ($stock['lastUpdatePriceEOW'] == '0000-00-00') {
        $startDate = '1900-01-01';
      } else {
        $date = date_create($stock['lastUpdatePriceEOW']);
        date_add($date, date_interval_create_from_date_string('1 days'));
        $startDate = date_format($date, 'Y-m-d');
      }
      $endDate = $today;
      $unit = 'w';
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
        SET lastUpdatePriceEOW = :today
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
  
  /**
   * @smart-auto-routing false
   * @url GET daysummary
   */ 
	public function getDaySummary() 
	{
    $query = new YahooFinanceQuery;
    $statement = "SELECT * FROM view_history_day_summary";
    return \Db::getResult($statement);
  }

  /**
   * @smart-auto-routing false
   * @url GET weeksummary
   */ 
	public function getWeekSummary() 
	{
    $query = new YahooFinanceQuery;
    $statement = "SELECT * FROM view_history_week_summary";
    return \Db::getResult($statement);
  }
  
}