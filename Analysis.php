<?php
use Luracast\Restler\RestException;

class Analysis
{
  /**
   * @smart-auto-routing false
   * @url POST ema-price
   */ 
	public function postEMAPrice($unit = 'd', $interval = 1, $limit = 0) 
	{
    $type = 'EMA-PRICE';
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
        SELECT H.*, ST.*
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
  
}