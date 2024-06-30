<?php
namespace BlueFission\Framework\Chat\Tools;

use App\Domain\Stock\Queries\getStockBySymbolQuerySql;

class StockDatabase extends BaseTool
{
    protected $name = "Stock DB";
    protected $description = "Useful for when you need to answer questions about stocks and their prices."
    public function execute($symbol): string
    {
        // Implement the logic to execute a SQL query here
        $stockData = (new getStockBySymbolQuerySql($symbol))->get();
        $result = "The price of " . $stockData['symbol'] . " is " . $stockData['price'] . " USD";

        return $result;
    }
}