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

    $statistic = new Statistic();
    $statistic->postSMA($unit, 5, $limit);
    $statistic->postSMA($unit, 20, $limit);
    $statistic->postEMA($unit, 5, $limit);
    $statistic->postEMA($unit, 20, $limit);
    
    $analysis = new Analysis();
    $analysis->postEMAEMA($unit, 5, 20, $limit);
    
    \TTOMail::createAndSendAdmin(
      'Daily Batch Finished !!! - ' . date('Y-m-d') . ' parm=' . $unit . "-" . $limit 
     ,json_encode($bind)
    );
  
    return "done";
  }
  
}