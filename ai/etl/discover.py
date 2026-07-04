"""
Schema discovery tool — inspect an unknown pharmacy DB.

Usage:
    python -m etl.discover --db pharmacie_galy_db
    python -m etl.discover --db pharmacie_galy_db --table sale
"""
import argparse
import sys
import os
sys.path.insert(0, os.path.dirname(os.path.dirname(__file__)))

import pymysql
from core.config import DB_HOST, DB_PORT, DB_USER, DB_PASSWORD


def connect(db_name: str):
    return pymysql.connect(
        host=DB_HOST, port=DB_PORT,
        user=DB_USER, password=DB_PASSWORD,
        database=db_name, charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
    )


def list_tables(conn):
    with conn.cursor() as cur:
        cur.execute("SHOW TABLES")
        return [list(r.values())[0] for r in cur.fetchall()]


def describe_table(conn, table: str):
    with conn.cursor() as cur:
        cur.execute(f"DESCRIBE `{table}`")
        return cur.fetchall()


def table_count(conn, table: str) -> int:
    with conn.cursor() as cur:
        cur.execute(f"SELECT COUNT(*) AS n FROM `{table}`")
        return cur.fetchone()["n"]


def sample_rows(conn, table: str, n: int = 3):
    with conn.cursor() as cur:
        cur.execute(f"SELECT * FROM `{table}` LIMIT {n}")
        return cur.fetchall()


def main():
    parser = argparse.ArgumentParser(description="Inspect source DB schema")
    parser.add_argument("--db",    required=True, help="Source database name")
    parser.add_argument("--table", default=None,  help="Show details for specific table")
    parser.add_argument("--sample", action="store_true", help="Show 3 sample rows per table")
    args = parser.parse_args()

    conn = connect(args.db)
    print(f"\n{'='*60}")
    print(f"Database: {args.db}")
    print(f"{'='*60}")

    if args.table:
        tables = [args.table]
    else:
        tables = list_tables(conn)
        print(f"\nTables ({len(tables)}):")
        for t in tables:
            n = table_count(conn, t)
            print(f"  {t:<40} {n:>10} rows")

    print()
    for table in tables:
        n = table_count(conn, table)
        print(f"\n── {table} ({n} rows) ──")
        cols = describe_table(conn, table)
        for col in cols:
            print(f"  {col['Field']:<30} {col['Type']:<25} {col.get('Key','')}")
        if args.sample and n > 0:
            print(f"  Sample rows:")
            for row in sample_rows(conn, table):
                print(f"    {dict(row)}")

    conn.close()


if __name__ == "__main__":
    main()
