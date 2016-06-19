<?php
use Luracast\Restler\RestException;

class Statistic
{
  /**
   * @smart-auto-routing false
   * @url POST sma-single-item
   */ 
	public function postSMASingleItem($ticker, $unit = 'd', $date, $interval = 1) 
	{
    $stat = 'SMA' . $interval;
    
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
      'ticker' => $ticker
     ,'unit'   => $unit
     ,'date'   => $date
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
      'ticker' => $ticker
     ,'unit'   => $unit
     ,'stat'   => $stat
     ,'date'   => $date
     ,'value'  => $value
    );
    $row_execute = \Db::execute($statement, $bind);
    return $row_execute;
  }

  /**
   * @smart-auto-routing false
   * @url POST sma-single
   */ 
	public function postSMASingle($ticker, $unit = 'd', $interval = 1) 
	{
    $stat = 'SMA' . $interval;
    
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
      'ticker' => $ticker
     ,'unit'   => $unit
     ,'stat'   => $stat
    );
    $priceList = \Db::getResult($statement, $bind);

    $count = 0;
    foreach ($priceList as $price) {
      $row_execute = $this->postSMASingleItem($ticker, $unit, $price['date'], $interval);
      $count = $count + $row_execute;
    }
    return $count;
  }
  
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
      $count = $this->postSMASingle($stock['ticker'], $unit, $interval);
      $response[$stock['ticker']] = $count;
    }
    return $response;
  }

  /**
   * @smart-auto-routing false
   * @url POST ema-single-init
   */ 
	public function postEMASingleInit($ticker, $unit = 'd', $interval = 1) 
	{
    $stat = 'EMA' . $interval;
    $statement = "
      SELECT 1
      FROM statistic
      WHERE ticker = :ticker
      AND unit = :unit
      AND stat = :stat
      AND value IS NOT NULL
      LIMIT 1
    ";
    $bind = array(
      'ticker' => $ticker
     ,'unit'   => $unit
     ,'stat'   => $stat
    );
    $found = \Db::getValue($statement, $bind);
    error_log('STOCKDBG $found = ' . $found);
    // No initial EMA yet
    if ($found != 1) {
      $statement = "
        SELECT * FROM history
         WHERE ticker = :ticker
           AND unit   = :unit
         ORDER BY date
         LIMIT " . $interval . "
      ";
      $bind = array(
        'ticker' => $ticker
       ,'unit'   => $unit
      );
      $priceList = \Db::getResult($statement, $bind);
      if (count($priceList) == $interval) {
        $count = 0;
        $total = 0;
        foreach ($priceList as $price) {
          $count++;
          if ($count == $interval) {
            $value = $total / $interval;
          } else {
            $value = null;
            $total = $total + $price['close'];
          }
          $statement = "
            INSERT INTO statistic (ticker, unit, stat, date, value)
            VALUES (:ticker, :unit, :stat, :date, :value)
          ";
          $bind = array(
            'ticker' => $ticker
           ,'unit'   => $unit
           ,'stat'   => $stat
           ,'date'   => $price['date']
           ,'value'  => $value
          );
          $row_execute = \Db::execute($statement, $bind);
        }
        return $count;
      } else {
        return 0;
      }
    } else {
      return 1;
    }
  }
  
  /**
   * @smart-auto-routing false
   * @url POST ema-single-item
   */ 
	public function postEMASingleItem($ticker, $unit = 'd', $interval = 1, $date, $lastEMA, $price) 
	{
    $stat = 'EMA' . $interval;
    
    if ($lastEMA == 0) {
      //Looking for previous EMA.
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
        'ticker' => $ticker
       ,'unit'   => $unit
       ,'stat'   => $stat
       ,'date'   => $date
      );
      $lastEMA = \Db::getValue($statement, $bind);
    }
    //Calculate current EMA base on yesterday EMA.
    $value = $lastEMA + ( (2 / ($interval+1)) * ($price - $lastEMA) );
    
    $statement = "
      INSERT INTO statistic (ticker, unit, stat, date, value)
      VALUES (:ticker, :unit, :stat, :date, :value);
    ";
    $bind = array(
      'ticker' => $ticker
     ,'unit'   => $unit
     ,'stat'   => $stat
     ,'date'   => $date
     ,'value'  => $value
    );
    $row_execute = \Db::execute($statement, $bind);
    $lastEMA = $value; //Hold this EMA for the next day price.
    return $lastEMA;
  }
  
  /**
   * @smart-auto-routing false
   * @url POST ema-single
   */ 
	public function postEMASingle($ticker, $unit = 'd', $interval = 1, $limit = 0) 
	{
    $stat = 'EMA' . $interval;

    $init = $this->postEMASingleInit($ticker, $unit, $interval);
    error_log('STOCKDBG $init = ' . $init);
   
    if ($init == 0) {
      return 'Not Enough Data';
    } else {
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
        'ticker' => $ticker
       ,'unit'   => $unit
       ,'stat'   => $stat
      );
      $priceList = \Db::getResult($statement, $bind);

      $count = 0;
      $lastEMA = 0;
      foreach ($priceList as $price) {
        $lastEMA = $this->postEMASingleItem($ticker, $unit, $interval, $price['date'], $lastEMA, $price['close']);
        $count++;
      }
      return $count;
    }
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
      $response[$stock['ticker']] = $this->postEMASingle($stock['ticker'], $unit, $interval);
    }
    return $response;
  }
  
}