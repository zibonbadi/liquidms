export default class Eventbus {
    constructor() {
        this.subscribers = [];
    }

    async send(message, data = {}) {
        if (typeof (message) != "string") {
            throw "Invalid message!";
        }
        if (typeof (data) != "object" && typeof (data) != "array") {
            throw "Invalid Payload!";
        }

        //console.log("Received message: ", message, data);

        for (let msg in this.subscribers) {
            if (msg == message) {
                for (let callback in this.subscribers[msg]) {
                    this.subscribers[msg][callback](message, data);
                }
            }
        }
    }

    async attach(message, callback) {
        if (this.subscribers[message] == undefined) {
            this.subscribers[message] = [];
        }
        this.subscribers[message].push(callback);
        return callback;
    }

    async detach(message, callback) {
        for (let msg in this.subscribers) {
            if (msg == message) {
                for (let cb in this.subscribers[msg]) {
                    if (callback === this.subscribers[msg][callback]) {
                        delete this.subscribers[msg][callback]
                        return true;
                    }
                }
            }
        }
    }

}

