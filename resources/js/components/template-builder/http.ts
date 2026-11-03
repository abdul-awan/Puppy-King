// Requests of the builder that are not page visits (image uploads and PDF previews).

// Laravel accepts the XSRF-TOKEN cookie back as the X-XSRF-TOKEN header.
export const xsrfToken = (): string => {
    const cookie = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
};

export const postForm = (url: string, body: FormData): Promise<Response> =>
    fetch(url, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: { 'X-XSRF-TOKEN': xsrfToken(), Accept: 'application/json' },
    });

export const postJson = (
    url: string,
    body: unknown,
    accept = 'application/json',
): Promise<Response> =>
    fetch(url, {
        method: 'POST',
        body: JSON.stringify(body),
        credentials: 'same-origin',
        headers: {
            'X-XSRF-TOKEN': xsrfToken(),
            'Content-Type': 'application/json',
            Accept: accept,
        },
    });
