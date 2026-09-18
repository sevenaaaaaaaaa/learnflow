const api = require('../../utils/api.js');
Page({
  data: { course: null, chapters: [], loading: true },
  onLoad(q) { this.slug = q.slug; },
  onShow() {
    api.student('course', { slug: this.slug }).then((r) => this.setData({ course: r.course, chapters: r.chapters, loading: false })).catch(() => this.setData({ loading: false }));
  },
  openLesson(e) {
    const id = e.currentTarget.dataset.id, locked = e.currentTarget.dataset.locked;
    if (locked) { wx.showToast({ title: '报名后可学习', icon: 'none' }); return; }
    wx.navigateTo({ url: '/pages/lesson/lesson?slug=' + this.slug + '&lesson=' + id });
  }
});
