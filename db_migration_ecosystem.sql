-- digiMind Ecosystem — Phase 2 DB Migration
-- Run on digipharmai_db
-- Tables: ai_suppliers, ai_deliveries, ai_delivery_items,
--         ai_purchase_orders, ai_purchase_order_items, ai_supplier_tokens

CREATE TABLE IF NOT EXISTS ai_suppliers (
  id                 int AUTO_INCREMENT PRIMARY KEY,
  pharmacy_id        int NOT NULL,
  source_supplier_id varchar(64) NOT NULL,
  name               varchar(200) NOT NULL,
  contact            varchar(255),
  synced_at          datetime DEFAULT NOW(),
  UNIQUE KEY uq_supplier (pharmacy_id, source_supplier_id),
  INDEX idx_pharmacy (pharmacy_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_deliveries (
  id                 bigint AUTO_INCREMENT PRIMARY KEY,
  pharmacy_id        int NOT NULL,
  source_delivery_id varchar(64) NOT NULL,
  supplier_id        varchar(64),
  supplier_name      varchar(200),
  delivery_date      date,
  status             varchar(30),
  source_created_at  datetime,
  synced_at          datetime DEFAULT NOW(),
  UNIQUE KEY uq_delivery (pharmacy_id, source_delivery_id),
  INDEX idx_pharmacy_supplier (pharmacy_id, supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_delivery_items (
  id                 bigint AUTO_INCREMENT PRIMARY KEY,
  pharmacy_id        int NOT NULL,
  delivery_id        bigint NOT NULL,
  source_delivery_id varchar(64),
  product_id         varchar(64),
  product_name       varchar(200),
  quantity           decimal(10,2),
  price_cession      decimal(12,2),
  public_price       decimal(12,2),
  validated          tinyint(1) DEFAULT 0,
  source_created_at  datetime,
  INDEX idx_delivery (delivery_id),
  INDEX idx_pharmacy (pharmacy_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_purchase_orders (
  id                      int AUTO_INCREMENT PRIMARY KEY,
  pharmacy_id             int NOT NULL,
  order_ref               varchar(25),
  supplier_name           varchar(200) NOT NULL,
  supplier_email          varchar(150),
  status                  enum('draft','sent','confirmed','declined','shipped','delivered','cancelled') DEFAULT 'draft',
  notes                   text,
  requested_delivery_date date,
  confirmed_delivery_date date,
  supplier_decline_reason text,
  sent_at                 datetime,
  confirmed_at            datetime,
  shipped_at              datetime,
  delivered_at            datetime,
  created_by              int,
  created_at              datetime DEFAULT NOW(),
  updated_at              datetime DEFAULT NOW() ON UPDATE NOW(),
  INDEX idx_pharmacy (pharmacy_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_purchase_order_items (
  id                 int AUTO_INCREMENT PRIMARY KEY,
  order_id           int NOT NULL,
  product_id         varchar(64),
  product_name       varchar(200) NOT NULL,
  quantity_requested int NOT NULL,
  quantity_confirmed int,
  unit_price         decimal(12,2),
  notes              text,
  INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_supplier_tokens (
  id         int AUTO_INCREMENT PRIMARY KEY,
  order_id   int NOT NULL,
  token      char(64) NOT NULL,
  expires_at datetime,
  created_at datetime DEFAULT NOW(),
  UNIQUE KEY uq_order (order_id),
  UNIQUE KEY uq_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Extend schema_map for all active data sources with delivery/supplier field names
UPDATE ai_data_sources
SET schema_map = JSON_SET(
    COALESCE(schema_map, '{}'),
    '$.delivery_table',        'delivery',
    '$.delivery_id_col',       'id',
    '$.delivery_supplier_fk',  'supplierId',
    '$.delivery_date_col',     'deliveryDate',
    '$.delivery_status_col',   'status',
    '$.delivery_items_table',  'delivery_items',
    '$.di_delivery_fk',        'deliveryId',
    '$.di_product_fk',         'productId',
    '$.di_quantity_col',       'quantity',
    '$.di_price_col',          'priceCession',
    '$.di_public_price_col',   'publicPrice',
    '$.di_validated_col',      'validated',
    '$.supplier_table',        'supplier',
    '$.supplier_id_col',       'id',
    '$.supplier_name_col',     'name',
    '$.supplier_contact_col',  'contact'
)
WHERE is_active = 1;
