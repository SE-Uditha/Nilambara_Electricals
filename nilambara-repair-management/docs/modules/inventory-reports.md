# Inventory reports

Current/default-location stock, low/out-of-stock filters, movement history, repair parts, POS parts, adjustments, receiving/purchase history and supplier purchases reuse existing spare parts, stock movements, source documents and suppliers. There is no second balance table.

Current availability is the signed movement sum. Low stock is positive availability at or below minimum stock (or reorder level when minimum is zero); out of stock is availability at or below zero. Movement rows retain references, dates, quantities and known costs. Product/category/supplier filters apply where supported. Current catalogue names appear in operating stock reports; immutable transaction receipts retain their saved descriptions.

Stock valuation is labelled unavailable pending an approved method such as FIFO or weighted average. A last-known transaction cost is not silently promoted to an approved valuation policy. Missing purchasing/inventory workflows remain dependencies; reports display existing records without inventing purchases, issues, costs or stock.
