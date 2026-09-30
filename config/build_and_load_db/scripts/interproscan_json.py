"""Read an InterProScan JSON one protein result at a time.

  from interproscan_json import read_results
  version, results = read_results('interproscan_results.json.gz')
  for result in results: ...

json.load of a whole gene set's JSON (2.2 GB for Congeria's 43,768 proteins) needs many times
that as Python objects and was killed for memory; this keeps one result in memory at a time.
Standard library only: the "results" array is found, then each element is decoded with
JSONDecoder.raw_decode from a buffer refilled as needed. Works on InterProScan's own output
and on merge_interproscan_json.py's (plain or gzipped). The version is read from the fields
before "results" (InterProScan writes "interproscan-version" first); '?' when it is not there.
"""

import gzip, json, re

CHUNK = 1 << 24   # characters read at a time


def open_text(path):
    return gzip.open(path, 'rt') if path.endswith('.gz') else open(path)


def read_results(path):
    handle = open_text(path)
    buffer = ''
    while True:
        match = re.search(r'"results"\s*:\s*\[', buffer)
        if match:
            break
        more = handle.read(CHUNK)
        if not more:
            handle.close()
            raise ValueError(f'{path}: no "results" array')
        buffer += more
    version = re.search(r'"interproscan-version"\s*:\s*"([^"]*)"', buffer[:match.start()])
    return (version.group(1) if version else '?'), _results(handle, buffer[match.end():], path)


def _results(handle, buffer, path):
    decoder = json.JSONDecoder()
    position = 0
    with handle:
        while True:
            # skip the separators between elements
            while position < len(buffer) and buffer[position] in ' \t\r\n,':
                position += 1
            if position < len(buffer) and buffer[position] == ']':
                return
            try:
                if position >= len(buffer):
                    raise ValueError('buffer used up')
                # every element is an object: one cut off at the buffer's end never decodes
                result, end = decoder.raw_decode(buffer, position)
            except ValueError:
                more = handle.read(CHUNK)
                if not more:
                    raise ValueError(f'{path}: truncated "results" array')
                buffer, position = buffer[position:] + more, 0
                continue
            yield result
            position = end
            if position > CHUNK:   # drop what has been decoded
                buffer, position = buffer[position:], 0
