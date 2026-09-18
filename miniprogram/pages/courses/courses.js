const api = require('../../utils/api.js');
Page({
  data: { courses: [], loading: true },
  onShow() {
    if (!getApp().globalData.token) { wx.navigateTo({ url: '/pages/login/login' }); return; }
    api.student('courses').then((r) => this.setData({ courses: r.courses, loading: false })).catch(() => this.setData({ loading: false }));
  },
  open(e) { wx.navigateTo({ url: '/pages/course/course?slug=' + e.currentTarget.dataset.slug }); }
});
