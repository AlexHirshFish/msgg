class LoginApp {
  constructor() {
    this.state = {
      activeView: 'login', // 'login' or 'register'
      phone: '',
      email: '',
      password: '',
      firstName: '',
      lastName: '',
      verificationCode: '',
      identifier: '', // для логина
      isLoading: false,
      error: '',
      success: ''
    };
  }

  setState(newState) {
    this.state = { ...this.state, ...newState };
    this.render();
  }

  async sendVerificationCode() {
    if (!this.validatePhone(this.state.phone)) {
      this.setState({ error: 'Введите корректный номер телефона' });
      return;
    }

    this.setState({ isLoading: true });

    try {
      const response = await fetch('/api/auth.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'send_verification',
          phone: this.state.phone
        })
      });

      const data = await response.json();

      if (data.success) {
        this.setState({
          success: 'Код подтверждения отправлен',
          isLoading: false
        });
      } else {
        this.setState({
          error: data.error || 'Ошибка отправки кода',
          isLoading: false
        });
      }
    } catch (error) {
      this.setState({
        error: 'Ошибка сети',
        isLoading: false
      });
    }
  }

  validatePhone(phone) {
    const cleaned = phone.replace(/\D/g, '');
    return cleaned.length >= 10;
  }

  async register() {
    if (!this.state.firstName || !this.state.lastName || !this.state.email || !this.state.password || !this.state.verificationCode) {
      this.setState({ error: 'Заполните все поля' });
      return;
    }

    this.setState({ isLoading: true });

    try {
      const response = await fetch('/api/auth.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'register',
          phone: this.state.phone,
          email: this.state.email,
          password: this.state.password,
          first_name: this.state.firstName,
          last_name: this.state.lastName,
          verification_code: this.state.verificationCode
        })
      });

      const data = await response.json();

      if (data.success) {
        localStorage.setItem('token', data.token);
        localStorage.setItem('user', JSON.stringify(data.user));
        window.location.href = 'messenger.html';
      } else {
        this.setState({
          error: data.error || 'Ошибка регистрации',
          isLoading: false
        });
      }
    } catch (error) {
      this.setState({
        error: 'Ошибка сети',
        isLoading: false
      });
    }
  }

  async login() {
    if (!this.state.identifier || !this.state.password) {
      this.setState({ error: 'Введите логин и пароль' });
      return;
    }

    this.setState({ isLoading: true });

    try {
      const response = await fetch('/api/auth.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'login',
          identifier: this.state.identifier,
          password: this.state.password
        })
      });

      const data = await response.json();

      if (data.success) {
        localStorage.setItem('token', data.token);
        localStorage.setItem('user', JSON.stringify(data.user));
        window.location.href = 'messenger.html';
      } else {
        this.setState({
          error: data.error || 'Неверные данные',
          isLoading: false
        });
      }
    } catch (error) {
      this.setState({
        error: 'Ошибка сети',
        isLoading: false
      });
    }
  }

  handleTelegramLogin() {
    // Здесь будет интеграция с Telegram Login Widget
    alert('Telegram login будет реализован отдельно');
  }

  render() {
    const container = document.getElementById('root');
    if (!container) return;

    container.innerHTML = `
      <div class="login-container">
        <div class="logo">
          <h1>Messenger</h1>
          <p>Быстрые и надежные сообщения</p>
        </div>

        ${this.state.activeView === 'login' ? this.renderLoginForm() : this.renderRegisterForm()}

        <div class="switch-view">
          <button onclick="loginApp.setState({activeView: '${this.state.activeView === 'login' ? 'register' : 'login'}'})">
            ${this.state.activeView === 'login' ? 'Регистрация' : 'Вход'}
          </button>
        </div>
      </div>
    `;
  }

  renderLoginForm() {
    return `
      <form onsubmit="event.preventDefault(); loginApp.login();">
        <div class="form-group">
          <label>Телефон или Email</label>
          <input 
            type="text" 
            placeholder="+7 999 123-45-67 или email@example.com"
            value="${this.state.identifier}"
            oninput="loginApp.setState({identifier: event.target.value, error: '', success: ''})"
          />
        </div>

        <div class="form-group">
          <label>Пароль</label>
          <input 
            type="password" 
            placeholder="Введите пароль"
            value="${this.state.password}"
            oninput="loginApp.setState({password: event.target.value, error: '', success: ''})"
          />
        </div>

        ${this.state.error ? `<div class="error-message">${this.state.error}</div>` : ''}
        ${this.state.success ? `<div class="success-message">${this.state.success}</div>` : ''}

        <button type="submit" ${this.state.isLoading ? 'disabled' : ''}>
          ${this.state.isLoading ? 'Загрузка...' : 'Войти'}
        </button>
      </form>

      <div class="divider">или</div>

      <button class="telegram-login" onclick="loginApp.handleTelegramLogin()">
        Войти через Telegram
      </button>
    `;
  }

  renderRegisterForm() {
    return `
      <form onsubmit="event.preventDefault(); loginApp.register();">
        <div class="form-group">
          <label>Номер телефона</label>
          <input 
            type="tel" 
            placeholder="+7 999 123-45-67"
            value="${this.state.phone}"
            oninput="loginApp.setState({phone: event.target.value, error: '', success: ''})"
          />
        </div>

        <button 
          type="button" 
          onclick="loginApp.sendVerificationCode()"
          ${!this.validatePhone(this.state.phone) ? 'disabled' : ''}
        >
          Отправить код
        </button>

        <div class="form-group">
          <label>Код подтверждения</label>
          <input 
            type="text" 
            placeholder="Введите 6-значный код"
            value="${this.state.verificationCode}"
            oninput="loginApp.setState({verificationCode: event.target.value, error: '', success: ''})"
          />
        </div>

        <div class="form-group">
          <label>Email</label>
          <input 
            type="email" 
            placeholder="email@example.com"
            value="${this.state.email}"
            oninput="loginApp.setState({email: event.target.value, error: '', success: ''})"
          />
        </div>

        <div class="form-group">
          <label>Имя</label>
          <input 
            type="text" 
            placeholder="Ваше имя"
            value="${this.state.firstName}"
            oninput="loginApp.setState({firstName: event.target.value, error: '', success: ''})"
          />
        </div>

        <div class="form-group">
          <label>Фамилия</label>
          <input 
            type="text" 
            placeholder="Ваша фамилия"
            value="${this.state.lastName}"
            oninput="loginApp.setState({lastName: event.target.value, error: '', success: ''})"
          />
        </div>

        <div class="form-group">
          <label>Пароль</label>
          <input 
            type="password" 
            placeholder="Минимум 6 символов"
            value="${this.state.password}"
            oninput="loginApp.setState({password: event.target.value, error: '', success: ''})"
          />
        </div>

        ${this.state.error ? `<div class="error-message">${this.state.error}</div>` : ''}
        ${this.state.success ? `<div class="success-message">${this.state.success}</div>` : ''}

        <button type="submit" ${this.state.isLoading ? 'disabled' : ''}>
          ${this.state.isLoading ? 'Загрузка...' : 'Зарегистрироваться'}
        </button>
      </form>
    `;
  }
}

// Инициализация приложения
let loginApp;
document.addEventListener('DOMContentLoaded', () => {
  loginApp = new LoginApp();
  loginApp.render();
});