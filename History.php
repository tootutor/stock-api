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

    switch ($unit) {
      case 'd':
        $decrement = '1 days';
        break;
      case 'w':
        $decrement = '1 weeks';
        break;
      case 'm':
        $decrement = '1 months';
        break;
    }
    
    $statement = "
      SELECT S.*, L.lastDate
      FROM stock AS S
      LEFT OUTER JOIN last_update AS L
      ON L.ticker = S.ticker
      AND L.unit = :unit
      WHERE (L.lastUpdateDate < :today OR L.lastUpdateDate IS NULL)
    ";
    if ($limit > 0) {
      $statement = $statement . ' LIMIT ' . $limit;
    }
    $bind = array(
      'today' => $today
     ,'unit'  => $unit 
    );
    $stockList = \Db::getResult($statement, $bind);

    ini_set('max_execution_time', 3000); //3000 seconds = 50 minutes
    $response = array();
    foreach ($stockList as $stock) {
      if ($stock['lastDate'] > '1900-01-01') {
        $date = date_create($stock['lastDate']);
        date_add($date, date_interval_create_from_date_string($decrement));
        $startDate = date_format($date, 'Y-m-d');
      } else {
        $startDate = '1900-01-01';
      }
      $endDate = $today;
      $dataList = $query->historicalQuote($stock['symbol'], $startDate, $endDate, $unit)->get();
      $count = 0;
      $lastDate = $stock['lastDate'];
      foreach($dataList as $data) {
        $statement = "
          SELECT 1 FROM history
          WHERE ticker = :ticker
          AND unit = :unit
          AND date = :date
        ";
        $bind = array(
          'ticker'   => $stock['ticker']
         ,'unit'     => $unit
         ,'date'     => $data['Date']
        );
        $exist = \Db::getValue($statement, $bind);

        if (!$exist) {
          $statement = "
            INSERT INTO history (ticker, unit, date, open, close, high, low, volume, adjclose)
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
          $row_execute = \Db::execute($statement, $bind);
        }        
        $count = $count + $row_execute;
        $lastDate = max($lastDate, $data['Date']);
      }
      $response[$stock['ticker']] = $count;
      
      if ($count > 0) {
        $statement = "
          UPDATE last_update
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
        
        if ($row_execute == 0) {
          $statement = "
            INSERT INTO last_update (ticker, unit, lastDate, lastUpdateDate)
            VALUES (:ticker, :unit, :lastDate, :lastUpdateDate)
          ";
          $row_execute = \Db::execute($statement, $bind);
        }
      }
    }
    
    return $response;
  }
  
}