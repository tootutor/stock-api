<?php
use Luracast\Restler\RestException;

class Statistic
{
  /**
   * @smart-auto-routing false
   * @url POST sma-single
   */ 
	public function postSMAItem($ticker, $unit = 'd', $date, $interval = 1) 
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
   * @url POST sma
   */ 
	public function postSMA($ticker, $unit = 'd', $interval = 1) 
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
      $row_execute = $this->postSMAItem($ticker, $unit, $price['date'], $interval);
      $count = $count + $row_execute;
    }
    return $count;
  }
  
  /**
   * @smart-auto-routing false
   * @url POST sma-all
   */ 
	public function postSMAAll($unit = 'd', $interval = 1, $limit = 0) 
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
      $count = $this->postSMA($stock['ticker'], $unit, $interval);
      $response[$stock['ticker']] = $count;
    }
    return $response;
  }

  /**
   * @smart-auto-routing false
   * @url POST ema-init
   */ 
	public function postEMAInit($ticker, $unit = 'd', $interval = 1) 
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
          $total = $total + $price['close'];
          if ($count == $interval) {
            $value = $total / $interval;
          } else {
            $value = null;
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
   * @url POST ema-item
   */ 
	public function postEMAItem($ticker, $unit = 'd', $interval = 1, $date, $lastEMA, $price) 
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
   * @url POST ema
   */ 
	public function postEMA($ticker, $unit = 'd', $interval = 1) 
	{
    $stat = 'EMA' . $interval;

    $init = $this->postEMAInit($ticker, $unit, $interval);
   
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
        $lastEMA = $this->postEMAItem($ticker, $unit, $interval, $price['date'], $lastEMA, $price['close']);
        $count++;
      }
      return $count;
    }
  }
  
  /**
   * @smart-auto-routing false
   * @url POST ema-all
   */ 
	public function postEMAAll($unit = 'd', $interval = 1, $limit = 0) 
	{
    $statement = "SELECT * FROM stock";
    if ($limit > 0) {
      $statement = $statement . ' LIMIT ' . $limit;
    }
    $stockList = \Db::getResult($statement);

    ini_set('max_execution_time', 30000); //3000 seconds = 500 minutes
    $response = array();
    foreach ($stockList as $stock) {
      $response[$stock['ticker']] = $this->postEMA($stock['ticker'], $unit, $interval);
    }
    return $response;
  }

  /**
   * @smart-auto-routing false
   * @url POST macd
   */ 
	public function postMACD($ticker, $unit = 'd', $interval1 = 12, $interval2 = 26) 
	{
    $stat = 'MACD' . $interval1 . '-' . $interval2;
    $stat1 = 'EMA' . $interval1;
    $stat2 = 'EMA' . $interval2;

    $statement = "
      INSERT INTO statistic (ticker, unit, stat, date, value)
      SELECT S1.ticker, S1.unit, :stat, S1.date, (S1.value - S2.value)
       FROM statistic AS S1
      INNER JOIN statistic AS S2
         ON S1.ticker = S2.ticker
        AND S1.date = S2.date
        AND S1.unit = S2.unit
        AND S2.stat = :stat2
        AND S2.value IS NOT NULL
      WHERE S1.ticker = :ticker
        AND S1.unit = :unit
        AND S1.stat = :stat1
        AND S1.value IS NOT NULL
        AND NOT EXISTS (
          SELECT 1 FROM statistic AS ST
          WHERE ST.ticker = S1.ticker
          AND ST.unit = S1.unit
          AND ST.date = S1.date
          AND ST.stat = :stat
        )
    ";
    $bind = array(
      'ticker' => $ticker
     ,'unit'   => $unit
     ,'stat'   => $stat
     ,'stat1'   => $stat1
     ,'stat2'   => $stat2
    );
    $row_execute = \Db::execute($statement, $bind);

    return $row_execute;
  }

  /**
   * @smart-auto-routing false
   * @url POST macd-all
   */ 
	public function postMACDAll($unit = 'd', $interval1 = 12, $interval2 = 26, $limit = 0) 
	{
    $statement = "SELECT * FROM stock";
    if ($limit > 0) {
      $statement = $statement . ' LIMIT ' . $limit;
    }
    $stockList = \Db::getResult($statement);

    ini_set('max_execution_time', 30000); //3000 seconds = 500 minutes
    $response = array();
    foreach ($stockList as $stock) {
      $response[$stock['ticker']] = $this->postMACD($stock['ticker'], $unit, $interval1, $interval2);
    }
    return $response;
  }

  /**
   * @smart-auto-routing false
   * @url POST signal-init
   */ 
	public function postSIGNALInit($ticker, $unit = 'd', $interval = 9, $interval1 = 12, $interval2 = 26) 
	{
    $stat = 'SIGNAL' . $interval . '-' . $interval1 . '-' . $interval2;
    $statBase = 'MACD' . $interval1 . '-' . $interval2;
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
    // No initial SIGNAL yet
    if ($found != 1) {
      $statement = "
        SELECT * FROM statistic
         WHERE ticker = :ticker
           AND unit   = :unit
           AND stat   = :statBase
         ORDER BY date
         LIMIT " . $interval . "
      ";
      $bind = array(
        'ticker'   => $ticker
       ,'unit'     => $unit
       ,'statBase' => $statBase
      );
      $MACDList = \Db::getResult($statement, $bind);
      if (count($MACDList) == $interval) {
        $count = 0;
        $total = 0;
        foreach ($MACDList as $MACD) {
          $count++;
          $total = $total + $MACD['value'];
          if ($count == $interval) {
            $value = $total / $interval;
          } else {
            $value = null;
          }
          $statement = "
            INSERT INTO statistic (ticker, unit, stat, date, value)
            VALUES (:ticker, :unit, :stat, :date, :value)
          ";
          $bind = array(
            'ticker' => $ticker
           ,'unit'   => $unit
           ,'stat'   => $stat
           ,'date'   => $MACD['date']
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
   * @url POST signal-item
   */ 
	public function postSIGNALItem(
    $ticker, $unit = 'd', $interval = 9, $interval1 = 12, $interval2 = 26, $date, $lastSIGNAL, $MACD) 
	{
    $stat = 'SIGNAL' . $interval . '-' . $interval1 . '-' . $interval2;
    
    if ($lastSIGNAL == 0) {
      //Looking for previous SIGNAL.
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
      $lastSIGNAL = \Db::getValue($statement, $bind);
    }
    //Calculate current EMA base on yesterday EMA.
    $value = $lastSIGNAL + ( (2 / ($interval+1)) * ($MACD - $lastSIGNAL) );
    
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
    $lastSIGNAL = $value; //Hold this SIGNAL for the next day value.
    return $lastSIGNAL;
  }
  
  /**
   * @smart-auto-routing false
   * @url POST signal
   */ 
	public function postSIGNAL($ticker, $unit = 'd', $interval = 9, $interval1 = 12, $interval2 = 26) 
	{
    $stat = 'SIGNAL' . $interval . '-' . $interval1 . '-' . $interval2;
    $statBase = 'MACD' . $interval1 . '-' . $interval2;

    $init = $this->postSIGNALInit($ticker, $unit, $interval, $interval1, $interval2);
   
    if ($init == 0) {
      return 'Not Enough Data';
    } else {
      $statement = "
        SELECT S.* FROM statistic AS S
        WHERE S.ticker = :ticker
          AND S.unit = :unit
          AND S.stat = :statBase
          AND NOT EXISTS (
            SELECT 1 FROM statistic AS ST
            WHERE ST.ticker = S.ticker
            AND ST.unit = S.unit
            AND ST.date = S.date
            AND ST.stat = :stat
          )
        ORDER BY date
      ";
      $bind = array(
        'ticker'   => $ticker
       ,'unit'     => $unit
       ,'stat'     => $stat
       ,'statBase' => $statBase
      );
      $MACDList = \Db::getResult($statement, $bind);

      $count = 0;
      $lastSIGNAL = 0;
      foreach ($MACDList as $MACD) {
        $lastSIGNAL = $this->postSIGNALItem(
          $ticker, 
          $unit, 
          $interval, 
          $interval1, 
          $interval2, 
          $MACD['date'], 
          $lastSIGNAL, 
          $MACD['value']
        );
        $count++;
      }
      return $count;
    }
  }
  
  /**
   * @smart-auto-routing false
   * @url POST singnal-all
   */ 
	public function postSIGNALAll($unit = 'd', $interval = 9, $interval1 = 12, $interval2 = 26, $limit = 0) 
	{
    $statement = "SELECT * FROM stock";
    if ($limit > 0) {
      $statement = $statement . ' LIMIT ' . $limit;
    }
    $stockList = \Db::getResult($statement);

    ini_set('max_execution_time', 30000); //3000 seconds = 500 minutes
    $response = array();
    foreach ($stockList as $stock) {
      $response[$stock['ticker']] = $this->postSIGNAL($stock['ticker'], $unit, $interval, $interval1, $interval2);
    }
    return $response;
  }
  
}