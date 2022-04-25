const button = document.getElementById('notifications');
button.addEventListener('click', () => {

})

const Notifications = {
    send: () => {
        return new new Promise((resolve, reject) => {
            Notification.requestPermission().then((result) => {
                if (result === 'granted') {
                    Notifications._push();
                }
            });
        })
    },

    _push: () => {
        const randomItem = Math.floor(Math.random() * games.length);
        const notifTitle = games[randomItem].name;
        const notifBody = `Created by ${games[randomItem].author}.`;
        const notifImg = `data/img/${games[randomItem].slug}.jpg`;
        const options = {
            body: notifBody,
            icon: notifImg,
        };
        new Notification(notifTitle, options);
        setTimeout(randomNotification, 30000);
    }
}