"""Offline guard (plan 2.1): block every network connection that leaves this machine.

`clipper run --offline` installs it before any stage runs. Loopback and Unix
sockets stay allowed (a local LLM server). Model files must already be in the
cache; a missing one fails with a clear error instead of downloading.
"""

from __future__ import annotations

import ipaddress
import socket

_installed = False
attempts: list[str] = []


class NetworkBlocked(ConnectionError):
    pass


def _is_local(address) -> bool:
    if not isinstance(address, tuple):
        return True  # AF_UNIX path
    host = str(address[0])
    if host in ("localhost",):
        return True
    try:
        return ipaddress.ip_address(host.split("%")[0]).is_loopback
    except ValueError:
        return False


def install() -> None:
    global _installed
    if _installed:
        return
    original_connect = socket.socket.connect
    original_connect_ex = socket.socket.connect_ex

    def connect(self, address):
        if not _is_local(address):
            attempts.append(str(address))
            raise NetworkBlocked(f"offline mode: blocked a connection to {address}")
        return original_connect(self, address)

    def connect_ex(self, address):
        if not _is_local(address):
            attempts.append(str(address))
            raise NetworkBlocked(f"offline mode: blocked a connection to {address}")
        return original_connect_ex(self, address)

    socket.socket.connect = connect
    socket.socket.connect_ex = connect_ex
    _installed = True
