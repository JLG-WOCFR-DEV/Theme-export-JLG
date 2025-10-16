# Prioritized Code Recommendations

## High Priority

### Stream S3 uploads instead of loading archives into memory
The S3 connector reads the entire export archive into memory before issuing the `PUT` request. On large sites this can easily exceed PHP memory limits and crash the export pipeline. Switching to a streaming upload (for example by wiring a `Requests::request` hook that feeds the body from a file handle) would cap memory usage to a small buffer and unlock larger exports.【F:theme-export-jlg/includes/class-tejlg-export-connectors.php†L307-L449】

### Add observability when remote connectors are skipped early
`handle_event()` exits silently whenever the payload is missing, unreadable, or when no connector is enabled. Operators have no trace explaining why off-site replication never happened. Recording a log entry or dispatching a dedicated action for each early return would surface configuration mistakes sooner and speed up support triage.【F:theme-export-jlg/includes/class-tejlg-export-connectors.php†L24-L68】

## Medium Priority

### Improve custom-endpoint host generation for S3 connectors
When a custom endpoint is provided without `force_path_style`, the current implementation appends the object key directly to the endpoint URL without injecting the bucket name. Many S3-compatible vendors expect virtual-host style URLs (e.g. `https://my-bucket.vendor.tld/object`); without additional logic the upload fails unless the integrator duplicates the bucket in the configured endpoint. Detecting this case and automatically adding the bucket segment, or defaulting to path-style URLs for such endpoints, would make the connector more resilient out of the box.【F:theme-export-jlg/includes/class-tejlg-export-connectors.php†L299-L334】

## Low Priority

### Remove the unused SFTP stream context or leverage it for connection reuse
`dispatch_sftp()` creates a stream context tied to the SSH session but never uses it when opening the remote file handle. Either wiring the context into the `fopen()` call (to enforce the intended session) or dropping the unused allocation would simplify the code path and avoid confusion during maintenance.【F:theme-export-jlg/includes/class-tejlg-export-connectors.php†L526-L538】
