const api = require('../../utils/api.js');
Page({
  data: { lesson: null, isVideo: false, isMp4: false, done: false },
  onLoad(q) { this.slug = q.slug; this.lessonId = q.lesson; },
  onShow() {
    api.student('lesson', { slug: this.slug, lesson: this.lessonId }).then((r) => {
      const l = r.lesson || {};
      this.setData({ lesson: l, isVideo: l.type === 'video' && !!l.video, isMp4: /\.mp4($|\?)/.test(l.video || ''), done: !!(r.state && r.state.done) });
    }).catch((e) => wx.showToast({ title: e.error || '加载失败', icon: 'none' }));
  },
  markDone() {
    api.student('progress', { course_id: this.courseId(), lesson_id: this.lessonId, done: true }, 'POST')
      .then(() => { this.setData({ done: true }); wx.showToast({ title: '已完成', icon: 'success' }); })
      .catch((e) => wx.showToast({ title: e.error || '失败', icon: 'none' }));
  },
  courseId() { return this.data.lesson && this.data.lesson.course_id ? this.data.lesson.course_id : ''; },
  openWeb(){
    var u = getApp().globalData.baseUrl + '/learn/' + this.slug + '?lesson=' + this.lessonId + '&st=' + getApp().globalData.token;
    wx.navigateTo({ url: '/pages/webview/webview?url=' + encodeURIComponent(u) });
  },
  copyLink() { wx.setClipboardData({ data: getApp().globalData.baseUrl + '/learn/' + this.slug }); }
});
