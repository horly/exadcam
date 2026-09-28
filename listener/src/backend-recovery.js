const temporaryStatuses = new Set([408, 429, 500, 502, 503, 504]);
const temporaryCodes = new Set(['ECONNREFUSED', 'ECONNRESET', 'ETIMEDOUT', 'EHOSTUNREACH', 'ENETUNREACH', 'EAI_AGAIN', 'UND_ERR_CONNECT_TIMEOUT', 'UND_ERR_HEADERS_TIMEOUT', 'UND_ERR_SOCKET']);

// Tag errors only at the registry/event boundary. A protocol/parser failure
// must never be mistaken for a transient backend failure.
export async function backendCall(operation) {
    try { return await operation(); }
    catch (error) {
        error.backendFailure = true;
        error.temporaryBackend = temporaryStatuses.has(error.status)
            || error.name === 'TimeoutError'
            || temporaryCodes.has(error.code)
            || temporaryCodes.has(error.cause?.code);
        throw error;
    }
}

export function isTemporaryBackendError(error) {
    return error?.backendFailure === true && error.temporaryBackend === true;
}
