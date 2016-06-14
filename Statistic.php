<?php
use Luracast\Restler\RestException;

class Statistic
{
  /**
   * @smart-auto-routing false
   * @url POST sma
   */ 
	public function postSMA($unit = 'd', $interval = 1, $limit = 0) 
	{
    $stat = 'SMA' . $interval;
    
    $statement = "SELECT * FROM stock";
    if ($limit > 0) {
      $statement = $statement . ' LIMIT ' . $limit;
    }
    $stockList = \Db::getResult($statement);

    ini_set('max_execution_time', 30000); //3000 seconds = 500 minutes
    $response = array();
    foreach ($stockList as $stock) {
      $statement = "
        SELECT * FROM history AS H
        WHERE ticker = :ticker
        AND unit = :unit
        AND NOT EXISTS (
          SELECT 1 FROM statistic AS ST
          WHERE ST.ticker = H.ticker
          AND ST.unit = H.unit
          AND ST.date = H.date
          AND ST.stat = :stat
        )
        ORDER BY date
      ";
      $bind = array(
        'ticker' => $stock['ticker']
       ,'unit'   => $unit
       ,'stat'   => $stat
      );
      $priceList = \Db::getResult($statement, $bind);

      $count = 0;
      foreach ($priceList as $price) {
        $statement = "
          SELECT 
            SUM(close) AS total
           ,COUNT(*) AS count
          FROM (
            SELECT H.close
            FROM history AS H
            WHERE ticker = :ticker
            AND unit = :unit
            AND date <= :date
            ORDER BY date DESC
            LIMIT " . $interval . "
          ) AS temp
        ";
        $bind = array(
          'ticker' => $stock['ticker']
         ,'unit'   => $unit
         ,'date'   => $price['date']
        );
        $summary = \Db::getRow($statement, $bind);
        
        if ((int)$summary['count'] == $interval) {
          $value = $summary['total'] / $interval;
        } else {
          $value = null;
        }
        
        $statement = "
          INSERT INTO statistic (ticker, unit, stat, date, value)
          VALUES (:ticker, :unit, :stat, :date, :value);
        ";
        $bind = array(
          'ticker' => $stock['ticker']
         ,'unit'   => $unit
         ,'stat'   => $stat
         ,'date'   => $price['date']
         ,'value'  => $value
        );
        $row_execute = \Db::execute($statement, $bind);
        
        $count = $count + $row_execute;
        if ($count <= $interval) {
          $bind['stat'] = 'EMA' . $interval;
          // insert for initial EMA
          $row_execute = \Db::execute($statement, $bind);
        }
      }
      $response[$stock['ticker']] = $count;

    }
    return $response;
  }

  /**
   * @smart-auto-routing false
   * @url POST ema
   */ 
	public function postEMA($unit = 'd', $interval = 1, $limit = 0) 
	{
    $stat = 'EMA' . $interval;
    
    $statement = "SELECT * FROM stock";
    if ($limit > 0) {
      $statement = $statement . ' LIMIT ' . $limit;
    }
    $stockList = \Db::getResult($statement);

    ini_set('max_execution_time', 30000); //3000 seconds = 500 minutes
    $response = array();
    foreach ($stockList as $stock) {
      $statement = "
        SELECT * FROM history AS H
        WHERE ticker = :ticker
        AND unit = :unit
        AND NOT EXISTS (
          SELECT 1 FROM statistic AS ST
          WHERE ST.ticker = H.ticker
          AND ST.unit = H.unit
          AND ST.date = H.date
          AND ST.stat = :stat
        )
        ORDER BY date
      ";
      $bind = array(
        'ticker' => $stock['ticker']
       ,'unit'   => $unit
       ,'stat'   => $stat
      );
      $priceList = \Db::getResult($statement, $bind);

      $count = 0;
      $lastEMA = 0;
      foreach ($priceList as $price) {
        if ($lastEMA == 0) {
          $statement = "
              SELECT ST.value
              FROM statistic AS ST
              WHERE ticker = :ticker
                AND unit = :unit
                AND stat = :stat
                AND date < :date
              ORDER BY date DESC
              LIMIT 1
          ";
          $bind = array(
            'ticker' => $stock['ticker']
           ,'unit'   => $unit
           ,'stat'   => $stat
           ,'date'   => $price['date']
          );
          $lastEMA = \Db::getValue($statement, $bind);
        }
        
        if ($lastEMA > 0) {
          $value = $lastEMA + ( (2 / ($interval+1)) * ($price['close'] - $lastEMA) );
          
          $statement = "
            INSERT INTO statistic (ticker, unit, stat, date, value)
            VALUES (:ticker, :unit, :stat, :date, :value);
          ";
          $bind = array(
            'ticker' => $stock['ticker']
           ,'unit'   => $unit
           ,'stat'   => $stat
           ,'date'   => $price['date']
           ,'value'  => $value
          );
          $row_execute = \Db::execute($statement, $bind);
          $count = $count + $row_execute;
          $lastEMA = $value; //Hold this EMA for the next price.
        }
      }
      $response[$stock['ticker']] = $count;

    }
    return $response;
  }
  
}