FROM golang:1.22-alpine AS build

WORKDIR /src

COPY go.mod ./
RUN go mod download

COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -o /out/go-stat .

FROM alpine:3.20

WORKDIR /app
COPY --from=build /out/go-stat /app/go-stat

EXPOSE 7011

CMD ["/app/go-stat"]

