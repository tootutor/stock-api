<?php
use Luracast\Restler\RestException;
use DirkOlbrich\YahooFinanceQuery\YahooFinanceQuery;

class Test
{
  /**
   * @smart-auto-routing false
   * @url GET localstock
   */ 
	public function getTest() 
	{
    $date = date_create('2016-06-11');
    date_add($date, date_interval_create_from_date_string('1 days'));
    return date_format($date, 'Y-m-d');
	}
  
}