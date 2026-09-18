const api = require('../../utils/api.js');
Page({
  data: { profile: null, courses: [] },
  onShow() {
    if (!getApp().globalData.token) { wx.navigateTo({ url: '/pages/login/login' }); return; }
    api.student('profile').then((r) => this.setData({ profile: r })).catch(() => {});
    api.student('my').then((r) => this.setData({ courses: r.courses })).catch(() => {});
  },
  open(e) { wx.navigateTo({ url: '/pages/course/course?slug=' + e.currentTarget.dataset.slug }); },
  logout() { getApp().clearToken(); wx.navigateTo({ url: '/pages/login/login' }); }
});
