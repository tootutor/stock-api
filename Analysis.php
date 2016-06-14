<?php
use Luracast\Restler\RestException;

class Analysis
{
  /**
   * @smart-auto-routing false
   * @url POST calsma
   */ 
	public function postCalSMA($unit = 'd', $interval = 1, $limit = 0) 
	{
    $stat = 'SMA' . $interval;
    
    $statement = "SELECT * FROM stock";
    if ($limit > 0) {
      $statement = $statement . ' LIMIT ' . $limit;
    }
    $stockList = \Db::getResult($statement);

    ini_set('max_execution_time', 3000); //3000 seconds = 50 minutes
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
      }
      $response[$stock['ticker']] = $count;

    }
    return $response;
  }
  
}