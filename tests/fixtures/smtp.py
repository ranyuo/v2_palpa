"""Servidor SMTPS local para pruebas, con autenticación y certificado verificado."""
import base64
import json
import socketserver
import ssl
import sys
from pathlib import Path

cert, key, output, port_file = map(Path, sys.argv[1:5])
implicit = len(sys.argv) < 6 or sys.argv[5] != 'starttls'
context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
context.load_cert_chain(cert, key)

class Handler(socketserver.StreamRequestHandler):
    def handle(self):
        def reply(text):
            self.wfile.write((text + '\r\n').encode())
            self.wfile.flush()
        reply('220 localhost prueba SMTP')
        authenticated = False
        recipient = sender = ''
        while line := self.rfile.readline():
            command = line.decode().strip()
            verb = command.split(' ', 1)[0].upper()
            if verb in ('EHLO', 'HELO'):
                reply('250-localhost')
                reply('250-STARTTLS')
                reply('250 AUTH LOGIN')
            elif verb == 'STARTTLS':
                reply('220 Iniciar TLS')
                self.connection = context.wrap_socket(self.connection, server_side=True)
                self.rfile = self.connection.makefile('rb')
                self.wfile = self.connection.makefile('wb')
            elif command.upper() == 'AUTH LOGIN':
                reply('334 ' + base64.b64encode(b'Username:').decode())
                user = base64.b64decode(self.rfile.readline().strip()).decode()
                reply('334 ' + base64.b64encode(b'Password:').decode())
                password = base64.b64decode(self.rfile.readline().strip()).decode()
                authenticated = user == 'notificaciones@proyectos.pe' and password == 'solo-prueba-local'
                reply('235 Autenticado' if authenticated else '535 Acceso denegado')
            elif verb == 'MAIL':
                if not authenticated:
                    reply('530 Autenticacion requerida')
                else:
                    sender = command[10:].strip('<>')
                    reply('250 OK')
            elif verb == 'RCPT':
                recipient = command[8:].strip('<>')
                reply('550 Destinatario rechazado' if recipient == 'reject@example.test' else '250 OK')
            elif verb == 'DATA':
                reply('354 Finalice con punto')
                lines = []
                while (data := self.rfile.readline()) != b'.\r\n':
                    if not data:
                        return
                    lines.append(data[1:] if data.startswith(b'..') else data)
                with output.open('a') as out:
                    out.write(json.dumps({'sender': sender, 'recipient': recipient, 'message': b''.join(lines).decode()}) + '\n')
                reply('250 Entregado al servidor de pruebas')
            elif verb == 'QUIT':
                reply('221 Hasta luego')
                return
            elif verb in ('RSET', 'NOOP'):
                reply('250 OK')
            else:
                reply('500 Comando desconocido')

class Server(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True
    def get_request(self):
        sock, addr = super().get_request()
        try:
            return (context.wrap_socket(sock, server_side=True) if implicit else sock), addr
        except Exception:
            sock.close()
            raise

with Server(('127.0.0.1', 0), Handler) as server:
    port_file.write_text(str(server.server_address[1]))
    server.serve_forever()
