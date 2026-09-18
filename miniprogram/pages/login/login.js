const api = require('../../utils/api.js');
Page({
  data: { email: '', password: '', name: '', mode: 'login', err: '' },
  onEmail(e) { this.setData({ email: e.detail.value }); },
  onPass(e) { this.setData({ password: e.detail.value }); },
  onName(e) { this.setData({ name: e.detail.value }); },
  toggle() { this.setData({ mode: this.data.mode === 'login' ? 'register' : 'login', err: '' }); },
  submit() {
    const d = this.data;
    api.auth(d.mode, { email: d.email, password: d.password, name: d.name }).then((res) => {
      getApp().setToken(res.token);
      wx.switchTab({ url: '/pages/courses/courses' });
    }).catch((e) => this.setData({ err: e.error || '失败' }));
  }
});
