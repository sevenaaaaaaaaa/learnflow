function app() { return getApp(); }
function student(action, data, method) {
  return new Promise(function (resolve, reject) {
    wx.request({
      url: app().globalData.baseUrl + '/api/student.php?action=' + action,
      method: method || 'GET', data: data || {},
      header: { 'Authorization': 'Bearer ' + app().globalData.token, 'Content-Type': 'application/json' },
      success: function (r) { (r.data && r.data.ok) ? resolve(r.data) : reject(r.data || { error: '请求失败' }); },
      fail: reject
    });
  });
}
function auth(action, data) {
  return new Promise(function (resolve, reject) {
    wx.request({
      url: app().globalData.baseUrl + '/api/auth.php',
      method: 'POST', data: Object.assign({ action: action }, data || {}),
      header: { 'Content-Type': 'application/json' },
      success: function (r) { (r.data && r.data.ok) ? resolve(r.data) : reject(r.data || { error: '请求失败' }); },
      fail: reject
    });
  });
}
module.exports = { student: student, auth: auth };
