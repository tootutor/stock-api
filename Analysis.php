<?php
use Luracast\Restler\RestException;

class Analysis
{
  /**
   * @smart-auto-routing false
   * @url POST price-ema
   */ 
	public function postPriceEMA($unit = 'd', $interval = 1, $limit = 0) 
	{
    $type = 'PRICE-EMA' . $interval;
    $stat = 'EMA' . $interval;
    
    $statement = "SELECT * FROM stock";
    if ($limit > 0) {
      $statement = $statement . ' LIMIT ' . $limit;
    }
    $stockList = \Db::getResult($statement);

    ini_set('max_execution_time', 30000); //30000 seconds = 500 minutes
    $response = array();
    foreach ($stockList as $stock) {
      $statement = "
        SELECT MAX(buyDate) AS lastBuyDate, MAX(sellDate) AS lastSellDate
        FROM analysis
        WHERE ticker = :ticker
        AND unit = :unit
        AND type = :type
      ";
      $bind = array(
        'ticker' => $stock['ticker']
       ,'unit'   => $unit
       ,'type'   => $type
      );
      $lastDate = \Db::getRow($statement, $bind);
      $startDate = max($lastDate['lastBuyDate'], $lastDate['lastSellDate'], '1900-01-01');
      
      $statement = "
        SELECT H.*, ST.value
        FROM history AS H
        INNER JOIN statistic AS ST
           ON ST.ticker = H.ticker
          AND ST.unit   = H.unit
          AND ST.date   = H.date
          AND ST.stat   = :stat
          AND ST.value IS NOT NULL
        WHERE H.ticker = :ticker
          AND H.unit = :unit
          AND H.date >= :startDate
      ";
      $bind = array(
        'ticker'    => $stock['ticker']
       ,'unit'      => $unit
       ,'stat'      => $stat
       ,'startDate' => $startDate
      );
      $priceList = \Db::getResult($statement, $bind);
      
      $count = 0;
      $arrayCount = count($priceList);
      // Loop start with the 2nd item 
      for ($i=1; $i<$arrayCount; $i++) {
        if ( ($priceList[$i-1]['close'] < $priceList[$i-1]['value'])
          && ($priceList[$i]['close']   > $priceList[$i]['value']  ) )
        {
          //Buying Point
          $statement = "
            INSERT INTO analysis(ticker, unit, type, buyDate, buyPrice)
            VALUES (:ticker, :unit, :type, :buyDate, :buyPrice)
          ";
          $bind = array(
            'ticker'    => $stock['ticker']
           ,'unit'      => $unit
           ,'type'      => $type
           ,'buyDate'   => $priceList[$i]['date']
           ,'buyPrice'  => $priceList[$i]['close']
          );
          $row_execute = \Db::execute($statement, $bind);
        } else {
          if ( ($priceList[$i-1]['close'] > $priceList[$i-1]['value'])
            && ($priceList[$i]['close']   < $priceList[$i]['value']  ) )
          {
            //Selling Point
            $statement = "
              UPDATE analysis
              SET sellDate    = :sellDate
                 ,sellPrice   = :sellPrice
                 ,diff        = :sellPrice - buyPrice
                 ,percentDiff = 100*(:sellPrice - buyPrice)/buyPrice
              WHERE ticker = :ticker
                AND unit   = :unit
                AND type   = :type
                AND sellDate IS NULL
                AND sellPrice IS NULL
            ";
            $bind = array(
              'ticker'    => $stock['ticker']
             ,'unit'      => $unit
             ,'type'      => $type
             ,'sellDate'  => $priceList[$i]['date']
             ,'sellPrice' => $priceList[$i]['close']
            );
            $row_execute = \Db::execute($statement, $bind);
          }
        }
        $count = $count + $row_execute;
      }
      $response[$stock['ticker']] = $count;
    }
    return $response;
  }

  /**
   * @smart-auto-routing false
   * @url POST ema-ema
   */ 
	public function postEMAEMA($unit = 'd', $interval1 = 5, $interval2 = 20, $limit = 0) 
	{
    $type = 'EMA' .$interval1 . '-EMA' . $interval2;
    $stat1 = 'EMA' . $interval1;
    $stat2 = 'EMA' . $interval2;
    
    $statement = "SELECT * FROM stock";
    if ($limit > 0) {
      $statement = $statement . ' LIMIT ' . $limit;
    }
    $stockList = \Db::getResult($statement);

    ini_set('max_execution_time', 30000); //30000 seconds = 500 minutes
    $response = array();
    foreach ($stockList as $stock) {
      $statement = "
        SELECT MAX(buyDate) AS lastBuyDate, MAX(sellDate) AS lastSellDate
        FROM analysis
        WHERE ticker = :ticker
        AND unit = :unit
        AND type = :type
      ";
      $bind = array(
        'ticker' => $stock['ticker']
       ,'unit'   => $unit
       ,'type'   => $type
      );
      $lastDate = \Db::getRow($statement, $bind);
      $startDate = max($lastDate['lastBuyDate'], $lastDate['lastSellDate'], '1900-01-01');
      
      $statement = "
        SELECT H.*, ST1.value AS value1, ST2.value AS value2
        FROM history AS H
        INNER JOIN statistic AS ST1
           ON ST1.ticker = H.ticker
          AND ST1.unit   = H.unit
          AND ST1.date   = H.date
          AND ST1.stat   = :stat1
          AND ST1.value IS NOT NULL
        INNER JOIN statistic AS ST2
           ON ST2.ticker = H.ticker
          AND ST2.unit   = H.unit
          AND ST2.date   = H.date
          AND ST2.stat   = :stat2
          AND ST2.value IS NOT NULL
        WHERE H.ticker = :ticker
          AND H.unit = :unit
          AND H.date >= :startDate
      ";
      $bind = array(
        'ticker'    => $stock['ticker']
       ,'unit'      => $unit
       ,'stat1'     => $stat1
       ,'stat2'     => $stat2
       ,'startDate' => $startDate
      );
      $priceList = \Db::getResult($statement, $bind);
      
      $count = 0;
      $arrayCount = count($priceList);
      // Loop start with the 2nd item 
      for ($i=1; $i<$arrayCount; $i++) {
        if ( ($priceList[$i-1]['value1'] < $priceList[$i-1]['value2'])
          && ($priceList[$i]['value1']   > $priceList[$i]['value2']  ) )
        {
          //Buying Point
          $statement = "
            INSERT INTO analysis(ticker, unit, type, buyDate, buyPrice)
            VALUES (:ticker, :unit, :type, :buyDate, :buyPrice)
          ";
          $bind = array(
            'ticker'    => $stock['ticker']
           ,'unit'      => $unit
           ,'type'      => $type
           ,'buyDate'   => $priceList[$i]['date']
           ,'buyPrice'  => $priceList[$i]['close']
          );
          $row_execute = \Db::execute($statement, $bind);
        } else {
          if ( ($priceList[$i-1]['value1'] > $priceList[$i-1]['value2'])
            && ($priceList[$i]['value1']   < $priceList[$i]['value2']  ) )
          {
            //Selling Point
            $statement = "
              UPDATE analysis
              SET sellDate    = :sellDate
                 ,sellPrice   = :sellPrice
                 ,diff        = :sellPrice - buyPrice
                 ,percentDiff = 100*(:sellPrice - buyPrice)/buyPrice
              WHERE ticker = :ticker
                AND unit   = :unit
                AND type   = :type
                AND sellDate IS NULL
                AND sellPrice IS NULL
            ";
            $bind = array(
              'ticker'    => $stock['ticker']
             ,'unit'      => $unit
             ,'type'      => $type
             ,'sellDate'  => $priceList[$i]['date']
             ,'sellPrice' => $priceList[$i]['close']
            );
            $row_execute = \Db::execute($statement, $bind);
          }
        }
        $count = $count + $row_execute;
      }
      $response[$stock['ticker']] = $count;
    }
    return $response;
  }  

  /**
   * @smart-auto-routing false
   * @url POST macd
   */ 
	public function postMACD($unit = 'd', $interval1 = 12, $interval2 = 26, $signal = 9, $limit = 0) 
	{
    $type = 'MACD' .$interval1 . '-' . $interval2 . '-' . $signal;
    $stat1 = 'EMA' . $interval1;
    $stat2 = 'EMA' . $interval2;
    $stat3 = 'EMA' . $signal;
    
    $statement = "SELECT * FROM stock";
    if ($limit > 0) {
      $statement = $statement . ' LIMIT ' . $limit;
    }
    $stockList = \Db::getResult($statement);

    ini_set('max_execution_time', 30000); //30000 seconds = 500 minutes
    $response = array();
    foreach ($stockList as $stock) {
      $statement = "
        SELECT MAX(buyDate) AS lastBuyDate, MAX(sellDate) AS lastSellDate
        FROM analysis
        WHERE ticker = :ticker
        AND unit = :unit
        AND type = :type
      ";
      $bind = array(
        'ticker' => $stock['ticker']
       ,'unit'   => $unit
       ,'type'   => $type
      );
      $lastDate = \Db::getRow($statement, $bind);
      $startDate = max($lastDate['lastBuyDate'], $lastDate['lastSellDate'], '1900-01-01');
      
      $statement = "
        SELECT H.*, ST1.value AS value1, ST2.value AS value2, ST3.value AS value3
        FROM history AS H
        INNER JOIN statistic AS ST1
           ON ST1.ticker = H.ticker
          AND ST1.unit   = H.unit
          AND ST1.date   = H.date
          AND ST1.stat   = :stat1
          AND ST1.value IS NOT NULL
        INNER JOIN statistic AS ST2
           ON ST2.ticker = H.ticker
          AND ST2.unit   = H.unit
          AND ST2.date   = H.date
          AND ST2.stat   = :stat2
          AND ST2.value IS NOT NULL
        INNER JOIN statistic AS ST3
           ON ST3.ticker = H.ticker
          AND ST3.unit   = H.unit
          AND ST3.date   = H.date
          AND ST3.stat   = :stat3
          AND ST3.value IS NOT NULL
        WHERE H.ticker = :ticker
          AND H.unit = :unit
          AND H.date >= :startDate
      ";
      $bind = array(
        'ticker'    => $stock['ticker']
       ,'unit'      => $unit
       ,'stat1'     => $stat1
       ,'stat2'     => $stat2
       ,'stat3'     => $stat3
       ,'startDate' => $startDate
      );
      $priceList = \Db::getResult($statement, $bind);
      
      $count = 0;
      $arrayCount = count($priceList);
      // Loop start with the 2nd item 
      for ($i=1; $i<$arrayCount; $i++) {
        if ( ( ($priceList[$i-1]['value1']-$priceList[$i-1]['value2']) < $priceList[$i-1]['value3'])
          && ( ($priceList[$i]['value1']-$priceList[$i]['value2']) > $priceList[$i]['value3']) )
        {
          //Buying Point
          $statement = "
            INSERT INTO analysis(ticker, unit, type, buyDate, buyPrice)
            VALUES (:ticker, :unit, :type, :buyDate, :buyPrice)
          ";
          $bind = array(
            'ticker'    => $stock['ticker']
           ,'unit'      => $unit
           ,'type'      => $type
           ,'buyDate'   => $priceList[$i]['date']
           ,'buyPrice'  => $priceList[$i]['close']
          );
          $row_execute = \Db::execute($statement, $bind);
        } else {
          if ( ( ($priceList[$i-1]['value1']-$priceList[$i-1]['value2']) > $priceList[$i-1]['value3'])
            && ( ($priceList[$i]['value1']-$priceList[$i]['value2']) < $priceList[$i]['value3']) )
          {
            //Selling Point
            $statement = "
              UPDATE analysis
              SET sellDate    = :sellDate
                 ,sellPrice   = :sellPrice
                 ,diff        = :sellPrice - buyPrice
                 ,percentDiff = 100*(:sellPrice - buyPrice)/buyPrice
              WHERE ticker = :ticker
                AND unit   = :unit
                AND type   = :type
                AND sellDate IS NULL
                AND sellPrice IS NULL
            ";
            $bind = array(
              'ticker'    => $stock['ticker']
             ,'unit'      => $unit
             ,'type'      => $type
             ,'sellDate'  => $priceList[$i]['date']
             ,'sellPrice' => $priceList[$i]['close']
            );
            $row_execute = \Db::execute($statement, $bind);
          }
        }
        $count = $count + $row_execute;
      }
      $response[$stock['ticker']] = $count;
    }
    return $response;
  }  

}