-- #!sqlite
-- # { inventorysynchronizer
-- #   { init
CREATE TABLE IF NOT EXISTS inventory (name VARCHAR(30) NOT NULL PRIMARY KEY, mainInventory TEXT NOT NULL, armorInventory TEXT NOT NULL, offHandInventory TEXT NOT NULL, selectedHotbar INT NOT NULL)
-- #   }

-- #   { get
-- #     :name string
SELECT * FROM inventory WHERE name = :name
-- #   }

-- #   { set
-- #     :name string
-- #     :main string
-- #     :armor string
-- #     :offHand string
-- #     :hotbar int
INSERT INTO inventory (name, mainInventory, armorInventory, offHandInventory, selectedHotbar) VALUES (:name, :main, :armor, :offHand, :hotbar)
-- #   }

-- #   { update
-- #     :name string
-- #     :main string
-- #     :armor string
-- #     :offHand string
-- #     :hotbar int
UPDATE inventory SET mainInventory = :main, armorInventory = :armor, offHandInventory = :offHand, selectedHotbar = :hotbar WHERE name = :name
-- #   }
-- # }