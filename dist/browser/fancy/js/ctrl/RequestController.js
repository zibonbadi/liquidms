export default class RequestController {
    constructor() {
    }

    async get(url = '') {
        if (typeof (url) != "string") {
            throw "Invalid URL";
        }
        return await fetch(url, {
            method: 'GET',
        }).then((response) => {
            if (response.ok) {
                return response.text();
            } else {
                throw response.text();
            }
        });
    }
}


