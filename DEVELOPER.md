# Developer Notes

## Run WordPress
Install Docker and Docker Compose.  https://www.docker.com/products/docker-desktop

Build and start the docker compose containers.
```
docker-compose up
```

Open <a href="http://localhost:8000">localhost:8000</a>

## Refresh WordPress
Delete the database and refresh the installation.

```
docker rm $(docker ps -aq --filter "name=wp-plugin")
docker volume rm wp-plugin_db_data
docker-compose up
```

