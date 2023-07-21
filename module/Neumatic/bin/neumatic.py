#!/usr/bin/python
import cobbler.api as capi
api_handle = capi.BootAPI()
print api_handle.status('text');
