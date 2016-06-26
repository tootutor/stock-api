<?php
use Luracast\Restler\RestException;

class Batch
{
  /**
   * @smart-auto-routing false
   * @url POST daily
   */ 
	public function postDaily($unit = 'd', $limit = 1) 
	{
    $history = new History();
    $history->postLoadPrice($unit, $limit);

    // Calculate statistic
    $statistic = new Statistic();

    $statistic->postSMAAll($unit, 5, $limit);
    $statistic->postSMAAll($unit, 20, $limit);
    $statistic->postEMAAll($unit, 5, $limit);
    
    $statistic->postEMAAll($unit, 20, $limit);
    $statistic->postEMAAll($unit, 12, $limit);
    $statistic->postEMAAll($unit, 26, $limit);
    
    $statistic->postMACDAll($unit, 12, 26, $limit);
    $statistic->postSIGNALAll($unit, 9, 12, 26, $limit);
    
    // Analyze statitic
    $analysis = new Analysis();

    $analysis->postPriceEMAAll($unit, 5, $limit);
    $analysis->postSMASMAAll($unit, 5, 20, $limit);
    $analysis->postEMAEMAAll($unit, 5, 20, $limit);
    $analysis->postMACDAll($unit, 12, 26, 9, $limit);
    
    \TTOMail::createAndSendAdmin(
      'Daily Batch Finished !!! - ' . date('Y-m-d') . ' parm=' . $unit . "-" . $limit 
     ,'Done !!!'
    );
  
    return "done";
  }
  
}