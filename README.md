# ServerMon

![](https://img.shields.io/badge/PHP-shell__exec-lightgrey?style=for-the-badge&logo=php)&ensp;
![Website](https://img.shields.io/website?url=https%3A%2F%2Fservermon.seeleo.com%2F&style=for-the-badge&label=servermon.seeleo.com)

![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/lzcapp/ServerMon/main.yml?style=for-the-badge)
&ensp; ![Docker Image Version](https://img.shields.io/docker/v/seeleo/servermon?style=for-the-badge)
&ensp;
![Docker Image Size](https://img.shields.io/docker/image-size/seeleo/servermon?style=for-the-badge)

## Demo

- [ServerMon.seeleo.com](https://ServerMon.seeleo.com/)

## Local

**PHP** & **shell__exec()** Required

## Docker

### Docker Hub

```
docker pull seeleo/servermon:latest
sudo docker run --name servermon -d -p 5001:80 --restart=always seeleo/servermon:latest
```

### Container Registry (GitHub)

```
docker pull ghcr.io/lzcapp/servermon:latest
sudo docker run --name servermon -d -p 5001:80 --restart=always ghcr.io/lzcapp/servermon:latest
```

## Screenshot

![Screenshot](https://user-images.githubusercontent.com/12462465/154803703-2f41f8d5-c72d-40fa-85d3-c39cd79a300a.png)
