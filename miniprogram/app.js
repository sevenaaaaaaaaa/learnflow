App({
  globalData: { baseUrl: 'https://nownexts.com/learnflow', token: '' },
  onLaunch() { this.globalData.token = wx.getStorageSync('lf_token') || ''; },
  setToken(t) { this.globalData.token = t; wx.setStorageSync('lf_token', t); },
  clearToken() { this.globalData.token = ''; wx.removeStorageSync('lf_token'); }
});
