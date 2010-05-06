#!/usr/local/bin/python3.1
import sys
import http.client
import mimetypes

xeserver = "##input your server address##" # ex) haneul.xpressengine.com
xelocation = "##input your location##" # ex) /xe  if you install your xe on the root just leave it blank ""

message = ""
for line in sys.stdin:
	message += line	

def get_content_type(filename):
    return mimetypes.guess_type(filename)[0] or 'application/octet-stream'

BOUNDARY = '----------ThIs_Is_tHe_bouNdaRY_$'
CRLF = '\r\n'

fields = []
fields.append(("module", "mailing"))
fields.append(("act","procMailingInsertMail"))
print(fields)
L = []

for (key, value) in fields:
	L.append('--' + BOUNDARY)
	L.append('Content-Disposition: form-data; name="%s"' % key)
	L.append('')
	L.append(value)

(key, filename, value) = ("message", "message.txt", message)
L.append('--' + BOUNDARY)
L.append('Content-Disposition: form-data; name="%s"; filename="%s"' % (key, filename))
L.append('Content-Type: %s' % get_content_type(filename))
L.append('')
L.append(value)

L.append('--' + BOUNDARY + '--')
L.append('')
body = CRLF.join(L)
content_type = 'multipart/form-data; boundary=%s' % BOUNDARY

conn = http.client.HTTPConnection(xeserver)
conn.putrequest("POST", xelocation+"/index.php")
conn.putheader('content-type', content_type)
conn.putheader('content-length', str(len(body)))
conn.endheaders()
conn.send(body.encode())
r1 = conn.getresponse()
print(r1.status, r1.reason)

	
#message = parser.parse(sys.stdin)

#for part in message.work():
#	file.write(part.get_content_type() + "\n")


