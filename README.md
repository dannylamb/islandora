# ![Islandora](https://cloud.githubusercontent.com/assets/2371345/25624809/f95b0972-2f30-11e7-8992-a8f135402cdc.png) Islandora
[![Build Status][1]](https://travis-ci.org/Islandora-CLAW/islandora)
[![Contribution Guidelines][2]](./CONTRIBUTING.md)
[![LICENSE][3]](./LICENSE)

## Introduction

CLAW's core Islandora module for Drupal 8.x

## Installation

For a fully automated install, see [claw-playbook](https://github.com/Islandora-Devops/claw-playbook).  If you're installing
manually, the REST configuration for both Nodes and Media need to be enabled with `jwt_auth` for authentication and both
`json` and `jsonld` formats. 

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
