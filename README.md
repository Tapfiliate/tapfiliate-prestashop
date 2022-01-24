# Local development

To run it locally:
1. Add tapfiliate app to the same docker network i.e. add this lines to docker-compose.dev.yml
```
 networks:
   default:
     external:
       name: tapfiliate-net
```
2. Comment the following line for caddy in tapfiliate app docker-compose.yml
`- "80:80"`
3. After building and running tapfiliate app, check network and copy CURRENT caddy ip value (i.e. 172.20.0.21) to prestashop docker-compose.yml to the `extra_hosts:` setting:
```
    - "dev.tap:172.20.0.21"
    - "app.dev.tap:172.20.0.21"
    - "john.dev.tap:172.20.0.21"
```
4. Check if you created `environment.php`
5. `docker-compose up`
