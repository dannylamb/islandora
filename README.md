# ![Islandora](https://cloud.githubusercontent.com/assets/2371345/25624809/f95b0972-2f30-11e7-8992-a8f135402cdc.png) Islandora
[![Build Status][1]](https://travis-ci.org/Islandora-CLAW/islandora)
[![Contribution Guidelines][2]](./CONTRIBUTING.md)
[![LICENSE][3]](./LICENSE)

## Introduction

CLAW's core Islandora module for Drupal 8.x

## Installation

For a fully automated install, see [claw-playbook](https://github.com/Islandora-Devops/claw-playbook).  If you're installing
manually, then _at a minimum_, the REST configuration for Nodes, Media, and Files need to be enabled with `jwt_auth` for
authentication on all methods.  The `json` and `jsonld` formats need to be enabled for for GET requests.  Only the `json` fomat
is required for POST, PATCH, and DELETE requests.

This can be done using the Rest UI module by setting granularity to 'Method'.  If you want to use GET requests through a browser,
you'll want to enable `cookie` authentication. And if you want to use a username/password with cURL, you'll want to enable `basic
authentication`.

![screenshot from 2018-03-09 10-09-57](https://user-images.githubusercontent.com/20773151/37212586-caf31dc8-2385-11e8-8122-1608dacbfb5f.png)

If you want to import the configuration through Drupal's configuration synchronization tools, you can use these yaml files
from claw-playbook for [Nodes](https://github.com/Islandora-Devops/claw-playbook/blob/master/roles/internal/webserver-app/files/rest.resource.entity.node.yml),
[Media](https://github.com/Islandora-Devops/claw-playbook/blob/master/roles/internal/webserver-app/files/rest.resource.entity.media.yml),
and [Files](https://github.com/Islandora-Devops/claw-playbook/blob/master/roles/internal/webserver-app/files/rest.resource.entity.file.yml).

## REST API

Islandora has a light, mostly RESTful HTTP API that relies heavily on Drupal's core Rest module.

### /media/{media}/source

You can PUT content to the `/media/{media}/source` endpoint to update the File associated with a Media.  The `Content-Type`
header is expected, as well as a `Content-Disposition` header of the form `attachment; filename="your_filename"` to indicate
the name to give the file.  Requests with empty bodies or no `Content-Length` header will be rejected.

Example usage:
```
curl -u admin:islandora -v -X PUT -H 'Content-Type: image/png' -H 'Content-Disposition: attachment; filename="my_image.png"' --data-binary @my_image.png localhost:8000/media/1/source
```

### /node/{node}/media/{field}/add/{bundle}

You can POST content to the `/node/{node}/media/{field}/add/{bundle}` endpoint to create a new Media of the specified bundle
using the POST body.  It will be associated with the specified Node using the field from the route. The `Content-Type`
header is expected, as well as a `Content-Disposition` header of the form `attachment; filename="your_filename"` to indicate
the name to give the file.  Requests with empty bodies or no `Content-Length` header will be rejected.

Example usage:
```
curl -v -u admin:islandora -H "Content-Type: image/jpeg" -H "Content-Disposition: attachment; filename=\"test.jpeg\"" --data-binary @test.jpeg http://localhost:8000/node/1/media/my_media_field/add/my_media_bundle
```

## Maintainers

Current maintainers:

* [Diego Pino](https://github.com/diegopino)
* [Jared Whiklo](https://github.com/whikloj)

## Development

If you would like to contribute, please get involved by attending our weekly 
[Tech Call][4]. We love to hear from you!

If you would like to contribute code to the project, you need to be covered by 
an Islandora Foundation [Contributor License Agreement][5] or 
[Corporate Contributor License Agreement][6]. Please see the 
[Contributors][7] pages on Islandora.ca for more information.

## License

[GPLv2](http://www.gnu.org/licenses/gpl-2.0.txt)

[1]: https://travis-ci.org/Islandora-CLAW/islandora.png?branch=8.x-1.x
[2]: http://img.shields.io/badge/CONTRIBUTING-Guidelines-blue.svg
[3]: https://img.shields.io/badge/license-GPLv2-blue.svg?style=flat-square
[4]: https://github.com/Islandora-CLAW/CLAW/wiki
[5]: http://islandora.ca/sites/default/files/islandora_cla.pdf
[6]: http://islandora.ca/sites/default/files/islandora_ccla.pdf
[7]: http://islandora.ca/resources/contributors
