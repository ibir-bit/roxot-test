package main

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"log"
	"net/http"
	"os"
	"strconv"
	"time"

	_ "github.com/jackc/pgx/v5/stdlib"
)

type statResponse struct {
	Status string `json:"status"`
	ID     int64  `json:"id,omitempty"`
	Error  string `json:"error,omitempty"`
}

func main() {
	dsn := getenv("DATABASE_URL", "postgres://app:app@127.0.0.1:15432/stat_pipeline?sslmode=disable")
	addr := getenv("HTTP_ADDR", ":7011")

	db, err := sql.Open("pgx", dsn)
	if err != nil {
		log.Fatal(err)
	}
	defer db.Close()

	if err := waitForDB(db); err != nil {
		log.Fatal(err)
	}

	http.HandleFunc("/health", handleHealth(db))
	http.HandleFunc("/stat", handleStat(db))

	log.Printf("go-stat listening on %s", addr)
	log.Fatal(http.ListenAndServe(addr, nil))
}

func handleHealth(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		ctx, cancel := context.WithTimeout(r.Context(), time.Second)
		defer cancel()

		if err := db.PingContext(ctx); err != nil {
			writeJSON(w, http.StatusServiceUnavailable, statResponse{Status: "error", Error: "db_unavailable"})
			return
		}

		writeJSON(w, http.StatusOK, statResponse{Status: "ok"})
	}
}

func handleStat(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodGet {
			writeJSON(w, http.StatusMethodNotAllowed, statResponse{Status: "error", Error: "method_not_allowed"})
			return
		}

		query := r.URL.Query()
		placementID := query.Get("placement")
		actionType := query.Get("actionType")
		requestID := query.Get("requestId")
		occurredAt := parseOccurredAt(query.Get("occurredAt"))

		price, _ := strconv.ParseFloat(query.Get("price"), 64)
		priceCents := int(price)

		var eventID int64
		err := db.QueryRowContext(
			r.Context(),
			`INSERT INTO raw_events (placement_id, action_type, price_cents, occurred_at, request_id)
			 VALUES ($1, $2, $3, $4, $5)
			 RETURNING id`,
			placementID,
			actionType,
			priceCents,
			occurredAt,
			nullableString(requestID),
		).Scan(&eventID)
		if err != nil {
			writeJSON(w, http.StatusInternalServerError, statResponse{Status: "error", Error: "insert_failed"})
			return
		}

		writeJSON(w, http.StatusAccepted, statResponse{Status: "accepted", ID: eventID})
	}
}

func parseOccurredAt(raw string) time.Time {
	if raw == "" {
		return time.Now().UTC()
	}

	parsed, err := time.Parse(time.RFC3339, raw)
	if err != nil {
		return time.Now().UTC()
	}

	return parsed.UTC()
}

func nullableString(value string) sql.NullString {
	return sql.NullString{String: value, Valid: value != ""}
}

func waitForDB(db *sql.DB) error {
	var lastErr error

	for i := 0; i < 20; i++ {
		ctx, cancel := context.WithTimeout(context.Background(), time.Second)
		lastErr = db.PingContext(ctx)
		cancel()

		if lastErr == nil {
			return nil
		}

		time.Sleep(500 * time.Millisecond)
	}

	if lastErr == nil {
		lastErr = errors.New("unknown database error")
	}

	return lastErr
}

func writeJSON(w http.ResponseWriter, status int, payload statResponse) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(payload)
}

func getenv(key string, fallback string) string {
	value := os.Getenv(key)
	if value == "" {
		return fallback
	}

	return value
}
