let socket;
let tryConnect = true;
let status = 'try';
const divStatus = document.querySelector('.js-status');

function getConnection() {
  if (tryConnect === true) return new WebSocket('ws://192.168.0.99:9090');
  return null;
}

function connect() {
  socket = getConnection();
  let time;
  if (socket !== null) {
    socket.onopen = (e) => {
      console.log('Connected');
      status = 'connected';
      setInterval(ping, 10000);
    };

    socket.onmessage = (e) => {
      if (e.data.toString() === '__pong__') {
        status = 'connected';
        pong();
        return;
      }

      const response = JSON.parse(e.data);

      if (response.error) {
        // close connection
        socket.close();
        socket = null;
        tryConnect = false;
      }

      // create list on table
      if (response.devices && response.devices.length) {
        const tBody = document.querySelector('tbody');
        tBody.innerHTML = '';
        response.devices.forEach((device) => {
          const tr = document.createElement('tr');
          tr.setAttribute('id', device.id);
          Object.values(device).forEach((element) => {
            const td = document.createElement('td');
            td.innerText = element;
            tr.append(td);
          });
          tBody.append(tr);
        });
      }

      // When device connection is closed notify MIEPP controller
      if (
        response.message &&
        response.message === 'Device connection is closed'
      ) {
        const tBody = document.querySelector('tbody');
        const trs = document.querySelectorAll('tbody tr');
        const idRemove = response.object.id;
        let arrayTr = Array.from(trs);
        arrayTr = arrayTr.filter((tr) => {
          if (+tr.getAttribute('id') !== +idRemove) {
            return tr;
          }
        });
        // insert list
        tBody.innerHTML = '';
        arrayTr.forEach((tr) => {
          tBody.append(tr);
        });
      }

      console.log(response);
    };

    socket.onerror = (e) => {
      console.log(e);
      status = 'error';
    };

    socket.onclose = (e) => {
      setInterval(() => {
        connect(status);
      }, 1000);

      status = 'error';
    };

    function ping() {
      if (status === 'connected') {
        return;
      }

      if (socket !== null) socket.send('__ping__');
      time = setTimeout(() => {
        status = 'try';
      }, 5000);
    }

    function pong() {
      clearTimeout(time);
    }
  }
}

connect();

function verifyStatus(status) {
  switch (status) {
    case 'connected':
      divStatus.style.backgroundColor = 'green';
      break;
    case 'error':
      divStatus.style.backgroundColor = 'red';
      break;
    case 'try':
      divStatus.style.backgroundColor = 'yellow';
      break;
    default:
  }
}

setInterval(() => {
  if (!navigator.onLine) {
    status = 'error';
  }
  verifyStatus(status);
}, 5000);

function authenticateDevice() {
  const btn = document.querySelector('.js-btn-1');
  btn.addEventListener('click', (e) => {
    socket.send(JSON.stringify({ app_id: 6, imei: '12345' }));
  });
}

function authenticateUser() {
  const btn = document.querySelector('.js-btn');
  btn.addEventListener('click', (e) => {
    socket.send(
      JSON.stringify({ app_id: 5, auth: 'mK0Hvr!0Bk5Z?3JpdGTX00T?GA' }),
    );
  });
}

function getDevices() {
  const btn = document.querySelector('.js-btn-2');
  btn.addEventListener('click', (e) => {
    socket.send(
      JSON.stringify({
        app_id: 5,
        auth: 'mK0Hvr!0Bk5Z?3JpdGTX00T?GAa',
        type: '1',
      }),
    );
  });
}

getDevices();

authenticateUser();

authenticateDevice();
